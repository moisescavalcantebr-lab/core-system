param(
    [string]$Config = "",
    [string]$Server = "",
    [string]$User = "",
    [string]$RemotePath = ""
)

$ErrorActionPreference = "Stop"

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$DefaultConfig = Join-Path $ScriptDir "deploy.local.ps1"

function Get-ConfigValue([hashtable]$ConfigValues, [string]$Key, [string]$Default = "") {
    if ($ConfigValues.ContainsKey($Key) -and $null -ne $ConfigValues[$Key]) {
        return [string]$ConfigValues[$Key]
    }

    return $Default
}

function Invoke-ServerNative([string]$Description, [scriptblock]$Command, [int]$Attempts = 3, [int]$DelaySeconds = 8) {
    for ($Attempt = 1; $Attempt -le $Attempts; $Attempt++) {
        Write-Host "[$Description] tentativa $Attempt/$Attempts" -ForegroundColor DarkCyan
        & $Command
        if ($LASTEXITCODE -eq 0) {
            return $true
        }
        if ($Attempt -lt $Attempts) {
            Write-Host "[$Description] falhou; aguardando $DelaySeconds segundos..." -ForegroundColor Yellow
            Start-Sleep -Seconds $DelaySeconds
        }
    }

    return $false
}

if ($Config -eq "" -and (Test-Path $DefaultConfig)) {
    $Config = $DefaultConfig
}

if ($Config -ne "") {
    if (!(Test-Path $Config)) {
        throw "Arquivo de configuracao nao encontrado: $Config"
    }

    $ConfigValues = . $Config

    if ($ConfigValues -isnot [hashtable]) {
        throw "O arquivo de configuracao deve retornar um hashtable."
    }

    if ($Server -eq "") {
        $Server = Get-ConfigValue $ConfigValues "Server"
    }
    if ($User -eq "") {
        $User = Get-ConfigValue $ConfigValues "User"
    }
    if ($RemotePath -eq "") {
        $RemotePath = Get-ConfigValue $ConfigValues "RemotePath"
    }
}

if ($Server -eq "") {
    throw "Servidor nao configurado. Informe -Server ou configure scripts\deploy.local.ps1"
}

if ($User -eq "") {
    $User = "root"
}

if ($RemotePath -eq "") {
    $RemotePath = "/opt/workspace"
}

$RemoteCommand = @"
set -e
if [ ! -d "$RemotePath/docker" ]; then
  echo "ERRO: pasta docker nao encontrada em $RemotePath/docker. Rode primeiro o deploy do servidor."
  exit 1
fi
cd "$RemotePath/docker"
echo "[docker] subindo containers"
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
echo "[docker] aguardando banco"
for i in `$(seq 1 60); do
  if docker exec app_db mysqladmin ping -uroot -proot --silent >/dev/null 2>&1; then
    break
  fi
  sleep 2
done
docker exec app_db mysqladmin ping -uroot -proot --silent >/dev/null
echo "[docker] aguardando app php"
for i in `$(seq 1 40); do
  if docker exec app_php php -v >/dev/null 2>&1; then
    break
  fi
  sleep 1
done
docker exec app_php php -v >/dev/null
echo "[docker] status"
docker compose -f docker-compose.yml -f docker-compose.prod.yml ps
"@

$SshArgs = @("-o", "ConnectTimeout=20", "${User}@${Server}", $RemoteCommand)
$Ok = Invoke-ServerNative "ssh" { & ssh.exe @SshArgs } 3 10
if (!$Ok) {
    throw "Falha ao subir containers do servidor."
}
