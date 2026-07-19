param(
    [switch]$SkipBuild
)

$ErrorActionPreference = "Stop"

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$Workspace = Split-Path -Parent $ScriptDir
$DockerDir = Join-Path $Workspace "docker"
$ComposeFile = Join-Path $DockerDir "docker-compose.yml"

if (!(Test-Path $ComposeFile)) {
    throw "docker-compose.yml nao encontrado em: $ComposeFile"
}

Write-Host "[docker] Recriando containers locais da pasta oficial..." -ForegroundColor Cyan
Write-Host "[docker] Workspace: $Workspace" -ForegroundColor Green
Write-Host "[docker] O volume do banco sera preservado." -ForegroundColor Yellow

$Containers = @("app_php", "app_pma", "app_db")
foreach ($Container in $Containers) {
    docker inspect $Container *> $null
    if ($LASTEXITCODE -eq 0) {
        Write-Host "[docker] Removendo container antigo: $Container" -ForegroundColor Yellow
        docker rm -f $Container
        if ($LASTEXITCODE -ne 0) {
            throw "Falha ao remover container antigo: $Container"
        }
    }
}

Push-Location $DockerDir
try {
    $Args = @("compose", "-f", "docker-compose.yml", "up", "-d")
    if (!$SkipBuild) {
        $Args += "--build"
    }

    docker @Args
    if ($LASTEXITCODE -ne 0) {
        throw "Falha ao subir Docker local."
    }
} finally {
    Pop-Location
}

Write-Host "[docker] Aguardando banco local..." -ForegroundColor Cyan
for ($i = 1; $i -le 60; $i++) {
    docker exec app_db mysqladmin ping -uroot -proot --silent *> $null
    if ($LASTEXITCODE -eq 0) {
        break
    }
    Start-Sleep -Seconds 2
}
docker exec app_db mysqladmin ping -uroot -proot --silent *> $null
if ($LASTEXITCODE -ne 0) {
    throw "Banco local nao ficou pronto."
}

Write-Host "[docker] Conferindo app local..." -ForegroundColor Cyan
docker exec app_php php -v *> $null
if ($LASTEXITCODE -ne 0) {
    throw "Container app_php nao respondeu."
}

$WorkspaceFull = [System.IO.Path]::GetFullPath($Workspace).TrimEnd("\", "/")
$InspectJson = docker inspect app_php
if ($LASTEXITCODE -ne 0) {
    throw "Nao foi possivel inspecionar app_php."
}

$Info = $InspectJson | ConvertFrom-Json
$Mount = $Info[0].Mounts | Where-Object { ([string]$_.Destination).TrimEnd("/") -eq "/var/www/html" } | Select-Object -First 1
if (!$Mount) {
    throw "app_php nao possui bind mount em /var/www/html."
}

$MountSource = [System.IO.Path]::GetFullPath(([string]$Mount.Source)).TrimEnd("\", "/")
if (![string]::Equals($MountSource, $WorkspaceFull, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw "app_php ainda aponta para outro workspace: $MountSource"
}

Write-Host "[docker] Docker local alinhado com a pasta oficial." -ForegroundColor Green
Write-Host "[docker] Acesse: http://127.0.0.1:8000" -ForegroundColor Green
