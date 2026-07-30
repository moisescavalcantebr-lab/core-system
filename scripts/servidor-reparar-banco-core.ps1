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

function Invoke-ServerCommand([string]$Description, [string]$Command, [int]$Attempts = 3) {
    $SshArgs = @("-o", "ConnectTimeout=20", "${User}@${Server}", $Command)
    $Ok = Invoke-ServerNative $Description { & ssh.exe @SshArgs } $Attempts 10
    if (!$Ok) {
        throw "Falha ao executar comando remoto: $Description"
    }
}

function Invoke-UploadFile([string]$Description, [string]$LocalPath, [string]$RemoteFile, [int]$Attempts = 3) {
    if (!(Test-Path $LocalPath)) {
        throw "Arquivo local nao encontrado: $LocalPath"
    }

    $ScpArgs = @("-o", "ConnectTimeout=20", $LocalPath, "${User}@${Server}:$RemoteFile")
    $Ok = Invoke-ServerNative $Description { & scp.exe @ScpArgs } $Attempts 10
    if (!$Ok) {
        throw "Falha ao enviar arquivo: $Description"
    }
}

Write-Host "[repair] reparando banco core em $Server" -ForegroundColor Cyan
Write-Host "[repair] versao do script: core-installer-runner-2026-07-19-db-check-scripts" -ForegroundColor DarkCyan

$InstallerLocalPath = Join-Path (Split-Path -Parent $ScriptDir) "app\console\install_core.php"
$InstallerRemotePath = "/tmp/install_core.php"
$RunnerLocalPath = Join-Path $ScriptDir "server-install-core-runner.sh"
$RunnerRemotePath = "/tmp/server-install-core-runner.sh"
$CoreDbCheckLocalPath = Join-Path $ScriptDir "server-core-db-check.sh"
$CoreDbCheckRemotePath = "/tmp/server-core-db-check.sh"
$ProjectDbCheckLocalPath = Join-Path $ScriptDir "server-project-db-check.sh"
$ProjectDbCheckRemotePath = "/tmp/server-project-db-check.sh"
$ProjectDbRepairLocalPath = Join-Path $ScriptDir "server-project-db-repair.sh"
$ProjectDbRepairRemotePath = "/tmp/server-project-db-repair.sh"
$AppEnvCheckLocalPath = Join-Path $ScriptDir "server-app-env-check.sh"
$AppEnvCheckRemotePath = "/tmp/server-app-env-check.sh"
$AppPdoCheckLocalPath = Join-Path $ScriptDir "server-app-pdo-check.sh"
$AppPdoCheckRemotePath = "/tmp/server-app-pdo-check.sh"

Invoke-ServerCommand "1/27 subir containers" "cd '$RemotePath/docker' && docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build"
Invoke-ServerCommand "2/27 aguardar MySQL" "docker exec app_db mysqladmin ping -uroot -proot --silent" 10
Invoke-UploadFile "3/27 enviar instalador core" $InstallerLocalPath $InstallerRemotePath
Invoke-UploadFile "4/27 enviar runner do instalador" $RunnerLocalPath $RunnerRemotePath
Invoke-UploadFile "5/27 enviar check banco core" $CoreDbCheckLocalPath $CoreDbCheckRemotePath
Invoke-UploadFile "6/27 enviar reparo usuario projetos" $ProjectDbRepairLocalPath $ProjectDbRepairRemotePath
Invoke-UploadFile "7/27 enviar check usuario projetos" $ProjectDbCheckLocalPath $ProjectDbCheckRemotePath
Invoke-UploadFile "8/27 enviar check env app" $AppEnvCheckLocalPath $AppEnvCheckRemotePath
Invoke-UploadFile "9/27 enviar check PDO app" $AppPdoCheckLocalPath $AppPdoCheckRemotePath
Invoke-ServerCommand "10/27 copiar instalador para app" "docker cp '$InstallerRemotePath' app_php:/tmp/install_core.php"
Invoke-ServerCommand "11/27 copiar runner para app" "docker cp '$RunnerRemotePath' app_php:/tmp/server-install-core-runner.sh"
Invoke-ServerCommand "12/27 copiar check banco core para db" "docker cp '$CoreDbCheckRemotePath' app_db:/tmp/server-core-db-check.sh"
Invoke-ServerCommand "13/27 copiar reparo usuario projetos para db" "docker cp '$ProjectDbRepairRemotePath' app_db:/tmp/server-project-db-repair.sh"
Invoke-ServerCommand "14/27 copiar check usuario projetos para db" "docker cp '$ProjectDbCheckRemotePath' app_db:/tmp/server-project-db-check.sh"
Invoke-ServerCommand "15/27 copiar check env para app" "docker cp '$AppEnvCheckRemotePath' app_php:/tmp/server-app-env-check.sh"
Invoke-ServerCommand "16/27 copiar check PDO para app" "docker cp '$AppPdoCheckRemotePath' app_php:/tmp/server-app-pdo-check.sh"
Invoke-ServerCommand "17/27 diagnosticar arquivos no app" "docker exec app_php ls -la /tmp/install_core.php /tmp/server-install-core-runner.sh /tmp/server-app-env-check.sh /tmp/server-app-pdo-check.sh"
Invoke-ServerCommand "18/27 diagnosticar php no app" "docker exec app_php php -v"
Invoke-ServerCommand "19/27 validar sintaxe do instalador" "docker exec app_php php -l /tmp/install_core.php"
Invoke-ServerCommand "20/27 executar instalador core" "docker exec app_php sh -lc 'sh /tmp/server-install-core-runner.sh || true'" 1
Invoke-ServerCommand "21/27 exibir log do instalador" "docker exec app_php sh /tmp/server-install-core-runner.sh --check" 1
Invoke-ServerCommand "22/27 validar banco core" "docker exec app_db sh /tmp/server-core-db-check.sh"
Invoke-ServerCommand "23/27 reparar usuario de projetos" "docker exec app_db sh /tmp/server-project-db-repair.sh"
Invoke-ServerCommand "24/27 validar env" "docker exec app_php sh /tmp/server-app-env-check.sh"
Invoke-ServerCommand "25/27 validar PDO" "docker exec app_php sh /tmp/server-app-pdo-check.sh"
Invoke-ServerCommand "26/27 validar usuario de projetos" "docker exec app_db sh /tmp/server-project-db-check.sh"
Invoke-ServerCommand "27/27 sincronizar paginas publicas" "docker exec app_php php /var/www/html/app/console/sync_pages.php"

Write-Host "[ok] Banco core reparado." -ForegroundColor Green
