param(
    [Parameter(Mandatory = $true)]
    [string]$Base,
    [string]$Config = "",
    [string]$Server = "",
    [string]$User = "",
    [string]$RemotePath = "",
    [switch]$Overwrite
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

if ($Base -notmatch "^[a-z0-9][a-z0-9_-]*$") {
    throw "Slug de base invalido: $Base"
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

$RemoteBasePath = "$RemotePath/bases/$Base"
$LocalBasesPath = Join-Path $Workspace "bases"
$LocalBasePath = Join-Path $LocalBasesPath $Base
$BackupDir = Join-Path $Workspace "_backups"
$TempRoot = Join-Path $Workspace "_tmp"
$TempPull = Join-Path $TempRoot ("base-pull-" + $Base + "-" + (Get-Date -Format "yyyyMMdd-HHmmss"))

Write-Host "[pull] Conferindo base no servidor: $RemoteBasePath" -ForegroundColor Cyan
$TestArgs = @("-o", "ConnectTimeout=20", "${User}@${Server}", "test -d '$RemoteBasePath'")
& ssh.exe @TestArgs
if ($LASTEXITCODE -ne 0) {
    throw "Base nao encontrada no servidor: $RemoteBasePath"
}

if ((Test-Path $LocalBasePath) -and !$Overwrite) {
    throw "A base ja existe no VS Code. Use -Overwrite para substituir com backup local."
}

New-Item -ItemType Directory -Force -Path $TempPull | Out-Null

Write-Host "[pull] Copiando base do servidor para area temporaria..." -ForegroundColor Cyan
$ScpSource = "${User}@${Server}:$RemoteBasePath"
$ScpArgs = @("-o", "ConnectTimeout=20", "-r", $ScpSource, $TempPull)
& scp.exe @ScpArgs
if ($LASTEXITCODE -ne 0) {
    throw "Falha ao copiar a base do servidor."
}

$PulledBasePath = Join-Path $TempPull $Base
if (!(Test-Path $PulledBasePath)) {
    $OnlyDirectory = Get-ChildItem -Path $TempPull -Directory | Select-Object -First 1
    if ($null -eq $OnlyDirectory) {
        throw "A copia da base nao gerou uma pasta valida."
    }
    $PulledBasePath = $OnlyDirectory.FullName
}

New-Item -ItemType Directory -Force -Path $LocalBasesPath | Out-Null

if (Test-Path $LocalBasePath) {
    New-Item -ItemType Directory -Force -Path $BackupDir | Out-Null
    $BackupZip = Join-Path $BackupDir ("base-" + $Base + "-" + (Get-Date -Format "yyyyMMdd-HHmmss") + ".zip")
    Write-Host "[pull] Criando backup local: $BackupZip" -ForegroundColor Yellow
    Compress-Archive -Path (Join-Path $LocalBasePath "*") -DestinationPath $BackupZip -Force

    $ResolvedLocalBase = (Resolve-Path $LocalBasePath).Path
    $ResolvedBasesRoot = (Resolve-Path $LocalBasesPath).Path
    if (!$ResolvedLocalBase.StartsWith($ResolvedBasesRoot, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw "Caminho de base local inseguro: $ResolvedLocalBase"
    }

    Remove-Item -LiteralPath $LocalBasePath -Recurse -Force
}

Write-Host "[pull] Atualizando base local: $LocalBasePath" -ForegroundColor Cyan
Move-Item -LiteralPath $PulledBasePath -Destination $LocalBasePath

Write-Host "[ok] Base '$Base' puxada para o VS Code." -ForegroundColor Green
Write-Host "Proximos passos recomendados:" -ForegroundColor Yellow
Write-Host "1. Testar localmente no Docker."
Write-Host "2. Rodar git status e revisar alteracoes."
Write-Host "3. Commitar e enviar ao GitHub."
Write-Host "4. Rodar deploy para o servidor."
