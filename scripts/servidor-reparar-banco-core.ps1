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

$InstallerLocalPath = Join-Path (Split-Path -Parent $ScriptDir) "app\console\install_core.php"
$InstallerRemotePath = "/tmp/install_core.php"

Invoke-ServerCommand "1/10 subir containers" "cd '$RemotePath/docker' && docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build"
Invoke-ServerCommand "2/10 aguardar MySQL" "docker exec app_db mysqladmin ping -uroot -proot --silent" 10
Invoke-UploadFile "3/10 enviar instalador core" $InstallerLocalPath $InstallerRemotePath
Invoke-ServerCommand "4/10 copiar instalador para app" "docker cp '$InstallerRemotePath' app_php:/tmp/install_core.php"
Invoke-ServerCommand "5/10 executar instalador core" "docker exec app_php php /tmp/install_core.php"
Invoke-ServerCommand "6/10 validar banco core" "docker exec app_php php /tmp/install_core.php"
Invoke-ServerCommand "7/10 validar schema core" "docker exec app_php php /tmp/install_core.php"
Invoke-ServerCommand "8/10 validar env" "docker exec app_php test -f /var/www/html/env/env.production.php"
Invoke-ServerCommand "9/10 validar PDO" "docker exec app_php php /tmp/install_core.php"
Invoke-ServerCommand "10/10 sincronizar paginas publicas" "docker exec app_php php /var/www/html/app/console/sync_pages.php"

Write-Host "[ok] Banco core reparado." -ForegroundColor Green
