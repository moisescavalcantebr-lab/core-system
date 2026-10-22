param([Parameter(Mandatory=$true)][string]$Backup, [switch]$Rollback)
$ErrorActionPreference = 'Stop'
$docker = 'C:\Program Files\Docker\Docker\resources\bin\docker.exe'
function Docker([string[]]$Arguments) {
    & $docker @Arguments
    if ($LASTEXITCODE -ne 0) { throw "Docker falhou: $($Arguments[0])" }
}
if ((& $docker context show) -ne 'desktop-linux') { throw 'Somente Docker local.' }
$state = Get-Content -LiteralPath (Join-Path $Backup 'state.json') -Raw | ConvertFrom-Json
if (!$state.RestoreVerified) { throw 'Backup sem restauracao validada.' }
$before = @(Get-Content -LiteralPath (Join-Path $Backup 'containers-private.json') -Raw | ConvertFrom-Json)
$old = @($before | Where-Object Name -eq '/app_pma')[0]
if (!$old) { throw 'Baseline do phpMyAdmin ausente.' }
$dbId = & $docker inspect app_db --format '{{.Id}}'
$appId = & $docker inspect app_php --format '{{.Id}}'
if ($dbId -ne $state.DatabaseContainer) { throw 'Banco mudou desde o backup.' }
$current = @(& $docker inspect app_pma | ConvertFrom-Json)[0]
if ($current.Config.Labels.'com.docker.compose.project' -ne 'docker' -or $current.HostConfig.PortBindings.'80/tcp'[0].HostIp -ne '127.0.0.1') { throw 'phpMyAdmin local inesperado.' }
$rollbackTag = 'core-pma-rollback:php83-' + (Split-Path $Backup -Leaf).Replace('php83-','')
Docker @('image','tag',$old.Image,$rollbackTag)
if (!(Test-Path -LiteralPath (Join-Path $Backup 'pma-before.tar'))) { Docker @('image','save','--output',(Join-Path $Backup 'pma-before.tar'),$rollbackTag) }
$image = 'core-phpmyadmin:5.2.3-php8.3.36'
$compose = @('compose','-p','docker','-f',(Join-Path (Split-Path $PSScriptRoot -Parent) 'docker/docker-compose.yml'))
if ($Rollback) { Docker @('image','tag',$rollbackTag,$image) }
else {
    Docker @('run','--rm','--network','none','--entrypoint','php',$image,'-r','if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 3 || version_compare(PHP_VERSION, "8.3.36", "<")) exit(1); echo PHP_VERSION, PHP_EOL;')
}
try {
    Docker ($compose + @('up','-d','--no-build','--no-deps','--force-recreate','phpmyadmin'))
    Docker @('exec','app_pma','apache2ctl','-t')
    if (!$Rollback) { Docker @('exec','app_pma','php','-r','if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 3 || version_compare(PHP_VERSION, "8.3.36", "<")) exit(1); echo PHP_VERSION, PHP_EOL;') }
    $after = @(& $docker inspect app_pma | ConvertFrom-Json)[0]
    if (($current.Config.Env | Sort-Object | ConvertTo-Json -Compress) -ne ($after.Config.Env | Sort-Object | ConvertTo-Json -Compress)) {
        # Image metadata naturally changes. Compare application/database settings separately.
        $envBefore = @($current.Config.Env | Where-Object { $_ -match '^(PMA_|MYSQL_)' } | Sort-Object)
        $envAfter = @($after.Config.Env | Where-Object { $_ -match '^(PMA_|MYSQL_)' } | Sort-Object)
        if (($envBefore -join "`n") -ne ($envAfter -join "`n")) { throw 'Configuracao PMA/MySQL mudou.' }
    }
    if (($after.HostConfig.PortBindings | ConvertTo-Json -Depth 5 -Compress) -ne ($current.HostConfig.PortBindings | ConvertTo-Json -Depth 5 -Compress)) { throw 'Portas mudaram.' }
    if ((& $docker inspect app_db --format '{{.Id}}') -ne $dbId -or (& $docker inspect app_php --format '{{.Id}}') -ne $appId) { throw 'Outro container mudou.' }
} catch {
    if (!$Rollback) {
        Docker @('image','tag',$rollbackTag,$image)
        Docker ($compose + @('up','-d','--no-build','--no-deps','--force-recreate','phpmyadmin'))
    }
    throw
}
Write-Output 'PASS: phpMyAdmin atualizado separadamente; Core e MySQL preservados.'
