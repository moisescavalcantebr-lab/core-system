param(
    [string]$Config = "",
    [string]$Server = "",
    [string]$User = "",
    [string]$RemotePath = ""
)

$ErrorActionPreference = "Stop"

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$Workspace = Split-Path -Parent $ScriptDir
$DefaultConfig = Join-Path $ScriptDir "deploy.local.ps1"

function Get-DeployConfigValue([hashtable]$DeployConfig, [string]$Key, [string]$Default = "") {
    if ($DeployConfig.ContainsKey($Key) -and $null -ne $DeployConfig[$Key]) {
        return [string]$DeployConfig[$Key]
    }

    return $Default
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

$LocalBasesPath = Join-Path $Workspace "bases"
$LocalBases = @()
if (Test-Path $LocalBasesPath) {
    $LocalBases = Get-ChildItem -Path $LocalBasesPath -Directory | Where-Object {
        if ($_.Name -eq "base") {
            return $false
        }

        $ManifestPath = Join-Path $_.FullName "base.json"
        return Test-Path $ManifestPath
    } | Select-Object -ExpandProperty Name
}

$RemoteCommand = @'
if docker exec app_db mysql -N -B -uroot -proot core -e "SHOW COLUMNS FROM bases LIKE 'base_stage';" | grep -q base_stage; then
  docker exec app_db mysql -N -B -uroot -proot core -e "SELECT slug FROM bases WHERE base_stage = 'published' AND slug <> 'base' ORDER BY slug;"
else
  docker exec app_db mysql -N -B -uroot -proot core -e "SELECT slug FROM bases WHERE is_protected = 1 AND slug <> 'base' ORDER BY slug;"
fi
'@
$RemoteCommand = "sh -lc " + "'" + ($RemoteCommand -replace "'", "'\''" -replace "`r`n", "`n" -replace "`r", "`n") + "'"
$SshArgs = @("-o", "BatchMode=yes", "-o", "ConnectTimeout=20", "${User}@${Server}", $RemoteCommand)

Write-Host "[guard] Validando bases publicadas do servidor..." -ForegroundColor Cyan
$PreviousErrorActionPreference = $ErrorActionPreference
$ErrorActionPreference = "Continue"
$RemoteOutput = & ssh.exe @SshArgs 2>&1
$ExitCode = $LASTEXITCODE
$ErrorActionPreference = $PreviousErrorActionPreference

if ($ExitCode -ne 0) {
    $RemoteText = $RemoteOutput -join [Environment]::NewLine
    if ($RemoteText -match "Permission denied|Host key verification failed|Could not resolve hostname|Connection timed out|No route to host") {
        Write-Host "[guard] Falha de conexao/autenticacao SSH com o servidor." -ForegroundColor Red
        Write-Host $RemoteText -ForegroundColor DarkYellow
        exit 2
    }

    Write-Host "[guard] Nao foi possivel consultar bases publicadas no servidor." -ForegroundColor Yellow
    Write-Host "[guard] Em servidor novo ou sem banco core, o deploy pode continuar." -ForegroundColor Yellow
    Write-Host $RemoteText -ForegroundColor DarkYellow
    exit 0
}

$RemoteProtectedBases = @(
    $RemoteOutput |
        ForEach-Object { [string]$_ } |
        Where-Object { $_ -ne "" -and $_ -notmatch "^mysql:" -and $_ -notmatch "^Warning:" } |
        ForEach-Object { $_.Trim() } |
        Where-Object { $_ -ne "" }
)

if ($RemoteProtectedBases.Count -eq 0) {
    Write-Host "[guard] Nenhuma base publicada registrada no servidor." -ForegroundColor Green
    exit 0
}

$Missing = @()
foreach ($Base in $RemoteProtectedBases) {
    if ($LocalBases -notcontains $Base) {
        $Missing += $Base
    }
}

if ($Missing.Count -gt 0) {
    Write-Host "[guard] Deploy bloqueado para proteger bases oficiais do servidor." -ForegroundColor Red
    Write-Host "[guard] Bases publicadas ausentes no VS Code:" -ForegroundColor Red
    foreach ($Base in $Missing) {
        Write-Host " - $Base" -ForegroundColor Red
    }
    Write-Host ""
    Write-Host "Sincronize ou remova com seguranca as bases publicadas do servidor antes do deploy:" -ForegroundColor Yellow
    Write-Host "powershell -ExecutionPolicy Bypass -File scripts\servidor-sincronizar-bases-git.ps1 -Overwrite" -ForegroundColor Yellow
    Write-Host "Se a base deve sair de producao, despublique/remova primeiro no servidor quando nao houver projetos vinculados; depois exclua no laboratorio." -ForegroundColor Yellow
    exit 1
}

Write-Host "[guard] Bases publicadas do servidor existem no laboratorio local." -ForegroundColor Green
