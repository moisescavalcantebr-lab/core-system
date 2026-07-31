param(
    [Parameter(Mandatory = $true)]
    [string]$BaseSlug,
    [string]$Config = "",
    [string]$Server = "",
    [string]$User = "",
    [string]$RemotePath = "",
    [switch]$ConfirmRemoval
)

$ErrorActionPreference = "Stop"

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$DefaultConfig = Join-Path $ScriptDir "deploy.local.ps1"

function Get-DeployConfigValue([hashtable]$DeployConfig, [string]$Key, [string]$Default = "") {
    if ($DeployConfig.ContainsKey($Key) -and $null -ne $DeployConfig[$Key]) {
        return [string]$DeployConfig[$Key]
    }

    return $Default
}

$BaseSlug = $BaseSlug.Trim().ToLowerInvariant()

if ($BaseSlug -notmatch '^[a-z0-9-]+$') {
    throw "Slug invalido. Use apenas letras minusculas, numeros e hifen."
}

if ($BaseSlug -eq "base") {
    throw "A base principal nao pode ser removida do servidor."
}

if (!$ConfirmRemoval) {
    throw "Use -ConfirmRemoval para confirmar a remocao da base publicada no servidor."
}

if ($Config -eq "" -and (Test-Path $DefaultConfig)) {
    $Config = $DefaultConfig
}

if ($Config -ne "") {
    if (!(Test-Path $Config)) {
        throw "Arquivo de configuracao nao encontrado: $Config"
    }

    $DeployConfig = & $Config
    if ($DeployConfig -isnot [hashtable]) {
        throw "O arquivo de configuracao deve retornar um hashtable."
    }

    if ($Server -eq "") {
        $Server = Get-DeployConfigValue $DeployConfig "Server"
    }
    if ($User -eq "") {
        $User = Get-DeployConfigValue $DeployConfig "User"
    }
    if ($RemotePath -eq "") {
        $RemotePath = Get-DeployConfigValue $DeployConfig "RemotePath"
    }
}

if ($Server -eq "") {
    throw "Servidor nao configurado. Informe -Server ou crie scripts\deploy.local.ps1."
}
if ($User -eq "") {
    $User = "root"
}
if ($RemotePath -eq "") {
    $RemotePath = "/opt/workspace"
}

$RemoteCommand = @"
set -e
BASE_SLUG="$BaseSlug"
REMOTE_PATH="$RemotePath"

case "`$BASE_SLUG" in
  ""|"base"|*[!a-z0-9-]*)
    echo "Slug invalido para remocao: `$BASE_SLUG"
    exit 1
    ;;
esac

BASE_ID=`$(docker exec app_db mysql -N -B -uroot -proot core -e "SELECT id FROM bases WHERE slug = '`$BASE_SLUG' LIMIT 1;" | head -1 | tr -d '\r')
if [ -z "`$BASE_ID" ]; then
  echo "[server-base-remove] Base nao registrada no servidor: `$BASE_SLUG"
  rm -rf "`$REMOTE_PATH/bases/`$BASE_SLUG"
  docker exec app_php rm -rf "/var/www/html/bases/`$BASE_SLUG"
  exit 0
fi

PROJECTS=`$(docker exec app_db mysql -N -B -uroot -proot core -e "SELECT COUNT(*) FROM projects WHERE base_id = `$BASE_ID AND status <> 'deleted';" | head -1 | tr -d '\r')
CLONES=`$(docker exec app_db mysql -N -B -uroot -proot core -e "SELECT COUNT(*) FROM bases WHERE cloned_from_id = `$BASE_ID;" | head -1 | tr -d '\r')

if [ "`$PROJECTS" -gt 0 ]; then
  echo "ERRO: base `$BASE_SLUG possui `$PROJECTS projeto(s) ativo(s). Remova ou migre os projetos antes."
  exit 1
fi

if [ "`$CLONES" -gt 0 ]; then
  echo "ERRO: base `$BASE_SLUG possui `$CLONES clone(s) vinculado(s). Remova os clones antes."
  exit 1
fi

docker exec app_db mysql -uroot -proot core -e "DELETE FROM bases WHERE id = `$BASE_ID LIMIT 1;"
rm -rf "`$REMOTE_PATH/bases/`$BASE_SLUG"
docker exec app_php rm -rf "/var/www/html/bases/`$BASE_SLUG"

echo "[server-base-remove] Base removida do servidor: `$BASE_SLUG"
"@

$RemoteCommand = $RemoteCommand -replace "`r`n", "`n" -replace "`r", "`n"
$SshArgs = @("-o", "BatchMode=yes", "-o", "ConnectTimeout=20", "${User}@${Server}", $RemoteCommand)

& ssh.exe @SshArgs
if ($LASTEXITCODE -ne 0) {
    throw "Falha ao remover a base publicada do servidor."
}
