param(
    [string]$Config = "",
    [string]$Server = "",
    [string]$User = "",
    [int]$LocalPort = 18080,
    [string]$RemoteHost = "127.0.0.1",
    [int]$RemotePort = 8080
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
}

if ($Server -eq "") {
    throw "Servidor nao configurado. Informe -Server ou configure scripts\deploy.local.ps1"
}

if ($User -eq "") {
    $User = "root"
}

$PortInUse = Get-NetTCPConnection -LocalPort $LocalPort -State Listen -ErrorAction SilentlyContinue
if ($PortInUse) {
    throw "A porta local $LocalPort ja esta em uso. Feche o processo atual ou informe outro -LocalPort."
}

Write-Host "Abrindo tunel SSH..." -ForegroundColor Cyan
Write-Host "Local:  http://127.0.0.1:$LocalPort" -ForegroundColor Green
Write-Host "Remoto: ${RemoteHost}:$RemotePort em $User@$Server" -ForegroundColor Green
Write-Host "Esta tarefa fica em execucao enquanto o tunel estiver aberto." -ForegroundColor Yellow
Write-Host "Para encerrar, pressione Ctrl+C." -ForegroundColor Yellow

$SshArgs = @(
    "-o", "ConnectTimeout=20",
    "-o", "ExitOnForwardFailure=yes",
    "-o", "ServerAliveInterval=30",
    "-N",
    "-L", "${LocalPort}:${RemoteHost}:${RemotePort}",
    "${User}@${Server}"
)

& ssh.exe @SshArgs
