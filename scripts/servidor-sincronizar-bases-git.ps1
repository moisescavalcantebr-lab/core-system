param(
    [string]$Config = "",
    [string]$Server = "",
    [string]$User = "",
    [string]$RemotePath = "",
    [switch]$AllBases,
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

function Invoke-Remote([string]$Description, [string]$Command) {
    Write-Host "[$Description]" -ForegroundColor Cyan
    & ssh.exe -o ConnectTimeout=20 "${User}@${Server}" $Command
    if ($LASTEXITCODE -ne 0) {
        throw "Falha ao executar comando remoto: $Description"
    }
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
$BackupDir = Join-Path $Workspace "_backups"
$TempRoot = Join-Path $Workspace "_tmp"
$Stamp = Get-Date -Format "yyyyMMdd-HHmmss"
$TempPullRoot = Join-Path $TempRoot "bases-sync-$Stamp"

New-Item -ItemType Directory -Force -Path $LocalBasesPath | Out-Null
New-Item -ItemType Directory -Force -Path $BackupDir | Out-Null
New-Item -ItemType Directory -Force -Path $TempPullRoot | Out-Null

$ResolvedBasesRoot = (Resolve-Path $LocalBasesPath).Path

if ($AllBases) {
    $ListCommand = "if [ -d '$RemotePath/bases' ]; then find '$RemotePath/bases' -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | sort; fi"
    Write-Host "[sync] Listando todas as bases do servidor..." -ForegroundColor Cyan
} else {
    $Sql = "SELECT slug FROM bases WHERE is_protected = 1 ORDER BY slug;"
    $ListCommand = "docker exec app_db mysql -N -B -uroot -proot core -e '$Sql'"
    Write-Host "[sync] Listando bases protegidas do servidor..." -ForegroundColor Cyan
}

$RemoteOutput = & ssh.exe -o ConnectTimeout=20 "${User}@${Server}" $ListCommand 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Host ($RemoteOutput -join [Environment]::NewLine) -ForegroundColor DarkYellow
    throw "Nao foi possivel listar as bases no servidor."
}

$Bases = @(
    $RemoteOutput |
        ForEach-Object { [string]$_ } |
        Where-Object { $_ -ne "" -and $_ -notmatch "^mysql:" -and $_ -notmatch "^Warning:" } |
        ForEach-Object { $_.Trim() } |
        Where-Object { $_ -match "^[a-z0-9][a-z0-9_-]*$" }
)

if ($Bases.Count -eq 0) {
    Write-Host "[sync] Nenhuma base para sincronizar." -ForegroundColor Yellow
    exit 0
}

foreach ($Base in $Bases) {
    $RemoteBasePath = "$RemotePath/bases/$Base"
    $TempBasePath = Join-Path $TempPullRoot $Base
    $LocalBasePath = Join-Path $LocalBasesPath $Base

    Write-Host "[sync] Baixando base: $Base" -ForegroundColor Cyan
    $RemoteSource = "${User}@${Server}:$RemoteBasePath"
    & scp.exe -r $RemoteSource $TempPullRoot
    if ($LASTEXITCODE -ne 0 -or !(Test-Path $TempBasePath)) {
        throw "Falha ao baixar a base do servidor: $Base"
    }

    if (Test-Path $LocalBasePath) {
        if (!$Overwrite) {
            Write-Host "[sync] Base ja existe localmente e foi ignorada: $Base. Use -Overwrite para substituir com backup." -ForegroundColor Yellow
            continue
        }

        $ResolvedLocalBase = (Resolve-Path $LocalBasePath).Path
        if (!$ResolvedLocalBase.StartsWith($ResolvedBasesRoot, [StringComparison]::OrdinalIgnoreCase)) {
            throw "Caminho local inseguro para substituir: $ResolvedLocalBase"
        }

        $BackupZip = Join-Path $BackupDir ("base-$Base-local-$Stamp.zip")
        Write-Host "[sync] Backup local antes de substituir: $BackupZip" -ForegroundColor Yellow
        Compress-Archive -Path (Join-Path $LocalBasePath "*") -DestinationPath $BackupZip -Force
        Remove-Item -LiteralPath $LocalBasePath -Recurse -Force
    }

    Move-Item -LiteralPath $TempBasePath -Destination $LocalBasePath
    Write-Host "[sync] Base sincronizada para VS Code/Git: bases/$Base" -ForegroundColor Green
}

if (Test-Path $TempPullRoot) {
    Remove-Item -LiteralPath $TempPullRoot -Recurse -Force
}

Write-Host ""
Write-Host "[sync] Conferindo alteracoes em bases/:" -ForegroundColor Cyan
& git -C $Workspace status --short bases
Write-Host ""
Write-Host "[sync] Proximo passo recomendado: testar local, git add/commit/push, depois deploy normal." -ForegroundColor Green
