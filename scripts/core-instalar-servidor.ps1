param(
    [string]$Config = "",
    [string]$Server = "",
    [string]$User = "",
    [string]$RemotePath = "",
    [switch]$SkipDeploy,
    [switch]$SkipStatus
)

$ErrorActionPreference = "Stop"

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$DeployScript = Join-Path $ScriptDir "deploy-atualizar-servidor.ps1"
$RepairScript = Join-Path $ScriptDir "servidor-reparar-banco-core.ps1"
$StatusScript = Join-Path $ScriptDir "servidor-status.ps1"

function New-CommonArgs {
    $Args = @()

    if ($Config -ne "") {
        $Args += @("-Config", $Config)
    }
    if ($Server -ne "") {
        $Args += @("-Server", $Server)
    }
    if ($User -ne "") {
        $Args += @("-User", $User)
    }
    if ($RemotePath -ne "") {
        $Args += @("-RemotePath", $RemotePath)
    }

    return $Args
}

function Invoke-Step([string]$Title, [string]$ScriptPath, [array]$Args) {
    if (!(Test-Path $ScriptPath)) {
        throw "Script nao encontrado: $ScriptPath"
    }

    Write-Host ""
    Write-Host "== $Title ==" -ForegroundColor Cyan
    & $ScriptPath @Args
}

$CommonArgs = New-CommonArgs

Write-Host "[core-install] instalacao/recuperacao do Core no servidor" -ForegroundColor Cyan

if (!$SkipDeploy) {
    Invoke-Step "1/3 Enviar arquivos oficiais para o servidor" $DeployScript $CommonArgs
} else {
    Write-Host "== 1/3 Deploy ignorado por parametro -SkipDeploy ==" -ForegroundColor Yellow
}

Invoke-Step "2/3 Criar/reparar banco core e aplicar schema" $RepairScript $CommonArgs

if (!$SkipStatus) {
    Invoke-Step "3/3 Validar servidor" $StatusScript $CommonArgs
} else {
    Write-Host "== 3/3 Status ignorado por parametro -SkipStatus ==" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "[core-install] concluido." -ForegroundColor Green
