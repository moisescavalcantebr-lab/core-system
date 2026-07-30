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
    $LocalBases = Get-ChildItem -Path $LocalBasesPath -Directory | Select-Object -ExpandProperty Name
}

$Sql = "SELECT slug FROM bases WHERE is_protected = 1 ORDER BY slug;"
$RemoteCommand = "docker exec app_db mysql -N -B -uroot -proot core -e '$Sql'"
$SshArgs = @("-o", "ConnectTimeout=20", "${User}@${Server}", $RemoteCommand)

Write-Host "[guard] Validando bases protegidas do servidor..." -ForegroundColor Cyan
$RemoteOutput = & ssh.exe @SshArgs 2>&1
$ExitCode = $LASTEXITCODE

if ($ExitCode -ne 0) {
    Write-Host "[guard] Nao foi possivel consultar bases protegidas no servidor." -ForegroundColor Yellow
    Write-Host "[guard] Em servidor novo ou sem banco core, o deploy pode continuar." -ForegroundColor Yellow
    Write-Host ($RemoteOutput -join [Environment]::NewLine) -ForegroundColor DarkYellow
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
    Write-Host "[guard] Nenhuma base protegida registrada no servidor." -ForegroundColor Green
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
    Write-Host "[guard] Bases protegidas ausentes no VS Code:" -ForegroundColor Red
    foreach ($Base in $Missing) {
        Write-Host " - $Base" -ForegroundColor Red
    }
    Write-Host ""
    Write-Host "Sincronize as bases protegidas do servidor com o VS Code/GitHub antes do deploy:" -ForegroundColor Yellow
    Write-Host "powershell -ExecutionPolicy Bypass -File scripts\servidor-sincronizar-bases-git.ps1 -Overwrite" -ForegroundColor Yellow
    Write-Host "Depois teste localmente, faca commit/push e rode o deploy normal." -ForegroundColor Yellow
    exit 1
}

Write-Host "[guard] Bases protegidas do servidor estao presentes no VS Code." -ForegroundColor Green
