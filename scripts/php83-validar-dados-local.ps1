param([Parameter(Mandatory=$true)][string]$Backup)
$ErrorActionPreference='Stop'
$docker='C:\Program Files\Docker\Docker\resources\bin\docker.exe'
function Docker([string[]]$Arguments) {
    $result=& $docker @Arguments
    if($LASTEXITCODE -ne 0){throw "Docker falhou: $($Arguments[0])"}
    return $result
}
if((Docker @('context','show')) -ne 'desktop-linux'){throw 'Somente Docker local.'}
$state=Get-Content -LiteralPath (Join-Path $Backup 'state.json') -Raw | ConvertFrom-Json
$db=@(Docker @('inspect','app_db') | ConvertFrom-Json)[0]
if($db.Id -ne $state.DatabaseContainer -or $db.Image -ne $state.DatabaseImage){throw 'Banco mudou desde o backup.'}
$envFile=Join-Path $Backup 'restore-verification.env'
@($db.Config.Env | Where-Object {$_.StartsWith('MYSQL_ROOT_PASSWORD=')}) | Set-Content -LiteralPath $envFile -Encoding utf8
$name='core-php83-verify-'+[Guid]::NewGuid().ToString('N').Substring(0,10)
$query=@'
SELECT CONCAT('CHECKSUM TABLE ',CHAR(96),REPLACE(TABLE_SCHEMA,CHAR(96),CONCAT(CHAR(96),CHAR(96))),CHAR(96),'.',CHAR(96),REPLACE(TABLE_NAME,CHAR(96),CONCAT(CHAR(96),CHAR(96))),CHAR(96),' EXTENDED;') FROM information_schema.TABLES WHERE TABLE_TYPE='BASE TABLE' AND TABLE_SCHEMA NOT IN ('mysql','sys','performance_schema','information_schema') ORDER BY TABLE_SCHEMA,TABLE_NAME
'@
$checksumCommand='export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"; umask 077; mysql -uroot -N -e "$1" > /tmp/php83-checks.sql; mysql -uroot -N < /tmp/php83-checks.sql; rm -f /tmp/php83-checks.sql'
try {
    Docker @('run','-d','--name',$name,'--network','none','--memory','768m','--env-file',$envFile,$db.Image,'--innodb-buffer-pool-size=64M','--performance-schema=OFF') | Out-Null
    $ready=$false
    for($attempt=0;$attempt -lt 90;$attempt++){
        & $docker exec $name sh -ec 'export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"; mysql -uroot -N -e "SELECT 1"' 2>$null | Out-Null
        if($LASTEXITCODE -eq 0){$ready=$true;break}
        Start-Sleep -Seconds 2
    }
    if(!$ready){throw 'MySQL de verificacao nao ficou pronto.'}
    Docker @('cp',(Join-Path $Backup 'all-databases.sql'),"${name}:/tmp/restore.sql") | Out-Null
    Docker @('exec',$name,'sh','-ec','export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"; mysql -uroot < /tmp/restore.sql') | Out-Null
    $original=@(Docker @('exec',$name,'sh','-ec',$checksumCommand,'sh',$query))
    $current=@(Docker @('exec','app_db','sh','-ec',$checksumCommand,'sh',$query))
    if($original.Count -eq 0 -or ($original -match '\sNULL$')){throw 'Checksum indisponivel.'}
    $original | Set-Content -LiteralPath (Join-Path $Backup 'all-tables-original.txt') -Encoding utf8
    $current | Set-Content -LiteralPath (Join-Path $Backup 'all-tables-current.txt') -Encoding utf8
    if(($original -join "`n") -ne ($current -join "`n")){throw 'Os checksums diferem. Revisar arquivos privados; nao restaurar banco automaticamente.'}
    Write-Output "PASS: $($original.Count) tabelas do Core e projetos identicas ao backup original restaurado."
} finally {
    & $docker container inspect $name > $null 2>&1
    if($LASTEXITCODE -eq 0){Docker @('rm','-f','-v',$name) | Out-Null}
    Remove-Item -LiteralPath $envFile -ErrorAction SilentlyContinue
}
