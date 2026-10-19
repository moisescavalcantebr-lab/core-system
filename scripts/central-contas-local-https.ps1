param(
    [string]$PrivateDirectory = (Join-Path (Split-Path $PSScriptRoot -Parent | Split-Path -Parent) 'private-mail-center'),
    [switch]$Build,
    [switch]$WithGoogleSecrets
)
$ErrorActionPreference = 'Stop'
$workspace = Split-Path $PSScriptRoot -Parent
foreach ($relative in @('tls/localhost.crt', 'tls/localhost.key', 'attachments')) {
    if (-not (Test-Path -LiteralPath (Join-Path $PrivateDirectory $relative))) {
        throw "Pasta privada incompleta: $relative. Consulte docs/central-de-contas-gmail-piloto.md."
    }
}
$docker = Get-Command docker -ErrorAction SilentlyContinue
$dockerPath = if ($docker) { $docker.Source } else { 'C:\Program Files\Docker\Docker\resources\bin\docker.exe' }
if (-not (Test-Path -LiteralPath $dockerPath)) { throw 'Docker Desktop não encontrado.' }
$previousPrivateDirectory = $env:MAIL_CENTER_PRIVATE_DIR
try {
    $env:MAIL_CENTER_PRIVATE_DIR = (Resolve-Path -LiteralPath $PrivateDirectory).Path.Replace('\', '/')
    $composeArguments = @('compose', '-f', (Join-Path $workspace 'docker/docker-compose.yml'), '-f', (Join-Path $workspace 'docker/docker-compose.mail-center.local.yml'))
    if ($WithGoogleSecrets) {
        if (-not (Test-Path -LiteralPath (Join-Path $PrivateDirectory 'secrets.json') -PathType Leaf)) { throw 'Arquivo privado secrets.json ainda não provisionado.' }
        $composeArguments += @('-f', (Join-Path $workspace 'docker/docker-compose.mail-center.example.yml'))
    }
    $composeArguments += @('up', '-d', '--no-deps')
    if ($Build) { $composeArguments += '--build' }
    & $dockerPath @composeArguments app
    if ($LASTEXITCODE -ne 0) { throw 'Não foi possível iniciar o HTTPS local.' }
    Write-Output 'Core local: https://localhost:8443/web/admin/login.php'
} finally {
    $env:MAIL_CENTER_PRIVATE_DIR = $previousPrivateDirectory
}
