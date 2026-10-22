param(
    [Parameter(Mandatory=$true)][string]$Backup,
    [string]$Image = 'core-php:8.3.36-security-local',
    [switch]$Rollback
)
$ErrorActionPreference = 'Stop'
$docker = 'C:\Program Files\Docker\Docker\resources\bin\docker.exe'
function Docker([string[]]$Arguments) {
    & $docker @Arguments
    if ($LASTEXITCODE -ne 0) { throw "Docker falhou: $($Arguments[0])" }
}
$state = Get-Content -LiteralPath (Join-Path $Backup 'state.json') -Raw | ConvertFrom-Json
if (!$state.RestoreVerified) { throw 'Backup sem restauracao validada.' }
if ((& $docker context show) -ne 'desktop-linux') { throw 'Somente Docker local.' }
$app = @(& $docker inspect app_php | ConvertFrom-Json)[0]
$db = @(& $docker inspect app_db | ConvertFrom-Json)[0]
if ($db.Id -ne $state.DatabaseContainer -or $db.Image -ne $state.DatabaseImage) { throw 'Banco mudou desde o backup; revisar antes de continuar.' }
if ($app.Config.Image -ne 'docker-app' -or $app.Config.Labels.'com.docker.compose.project' -ne 'docker') { throw 'Stack local inesperada.' }
$files = $app.Config.Labels.'com.docker.compose.project.config_files'.Split(',')
if (($files -join '|') -ne ($state.ComposeFiles -join '|')) { throw 'Arquivos Compose mudaram desde o backup.' }
$compose = @('compose','-p','docker')
foreach ($file in $files) { $compose += @('-f',$file) }
$privateBefore = $env:MAIL_CENTER_PRIVATE_DIR
$env:MAIL_CENTER_PRIVATE_DIR = $state.PrivateDirectory
function MountSignature($mounts) {
    @($mounts | Sort-Object Destination | Select-Object Type,@{Name='Source';Expression={ [IO.Path]::GetFullPath($_.Source).ToLowerInvariant() }},Destination,RW) | ConvertTo-Json -Compress
}
$mountsBefore = MountSignature $app.Mounts
$portsBefore = $app.HostConfig.PortBindings | ConvertTo-Json -Depth 5 -Compress
try {
    Docker ($compose + @('config','--quiet'))
    $target = if ($Rollback) { $state.RollbackTag } else { $Image }
    if (!$Rollback) {
        Docker @('run','--rm','--network','none','--entrypoint','php',$target,'/usr/local/lib/core-verify-runtime.php')
        Docker @('run','--rm','--network','none','--entrypoint','apache2ctl',$target,'-t')
    }
    Docker @('image','tag',$target,'docker-app')
    try {
        Docker ($compose + @('up','-d','--no-build','--no-deps','--force-recreate','app'))
        $after = @(& $docker inspect app_php | ConvertFrom-Json)[0]
        $mountsAfter = MountSignature $after.Mounts
        if ($mountsBefore -ne $mountsAfter -or $portsBefore -ne ($after.HostConfig.PortBindings | ConvertTo-Json -Depth 5 -Compress)) { throw 'Mounts ou portas mudaram.' }
        if ((& $docker inspect app_db --format '{{.Id}}') -ne $db.Id) { throw 'Container de banco mudou.' }
        Docker @('exec','app_php','apache2ctl','-t')
        if (!$Rollback) { Docker @('exec','app_php','php','/usr/local/lib/core-verify-runtime.php') }
    } catch {
        if (!$Rollback) {
            Write-Warning 'Validacao falhou; restaurando somente a imagem PHP anterior.'
            Docker @('image','tag',$state.RollbackTag,'docker-app')
            Docker ($compose + @('up','-d','--no-build','--no-deps','--force-recreate','app'))
        }
        throw
    }
    Docker @('exec','app_php','php','-v')
    Write-Output 'PASS: somente PHP recriado; mounts, portas e container MySQL preservados.'
} finally { $env:MAIL_CENTER_PRIVATE_DIR = $privateBefore }
