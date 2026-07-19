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

function Invoke-ServerCommand([string]$Description, [string]$Command) {
    Write-Host "[$Description]" -ForegroundColor Cyan
    $SshArgs = @("-o", "ConnectTimeout=20", "${User}@${Server}", $Command)
    $Ok = Invoke-ServerNative $Description { & ssh.exe @SshArgs } 3 10
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

Invoke-ServerCommand "path" "echo '[path] $RemotePath'; test -d '$RemotePath/docker'"
Invoke-ServerCommand "docker containers" "cd '$RemotePath/docker' && docker compose -f docker-compose.yml -f docker-compose.prod.yml ps"

$AppEnvCommand = "docker exec app_php sh -lc 'test -f /var/www/html/env/env.production.php && echo env.production.php OK || (echo ENV AUSENTE && exit 1)'"
$CheckLocalPath = Join-Path (Split-Path -Parent $ScriptDir) "app\console\check_core.php"
$CheckRemotePath = "/tmp/check_core.php"

Invoke-ServerCommand "app env" $AppEnvCommand
Invoke-ServerCommand "db databases" "docker exec app_db mysql -uroot -proot -e 'SHOW DATABASES;'"
Invoke-ServerCommand "db core tables" "docker exec app_db mysql -uroot -proot -e 'SHOW TABLES FROM core;'"
Invoke-UploadFile "enviar check core" $CheckLocalPath $CheckRemotePath
Invoke-ServerCommand "copiar check core para app" "docker cp '$CheckRemotePath' app_php:/tmp/check_core.php"
Invoke-ServerCommand "app pdo" "docker exec app_php php /tmp/check_core.php"
Invoke-ServerCommand "http localhost headers" "curl -I --max-time 5 http://127.0.0.1/"
Invoke-ServerCommand "http localhost body" "curl -s --max-time 5 http://127.0.0.1/web/ | head -20"
