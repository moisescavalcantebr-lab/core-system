param([string]$BackupRoot = (Join-Path (Split-Path $PSScriptRoot -Parent) '..\private-core-backups'))
$ErrorActionPreference = 'Stop'
$docker = 'C:\Program Files\Docker\Docker\resources\bin\docker.exe'
$workspace = Split-Path $PSScriptRoot -Parent
function Docker([string[]]$Arguments) {
    $result = & $docker @Arguments
    if ($LASTEXITCODE -ne 0) { throw "Docker falhou: $($Arguments[0])" }
    return $result
}
if ((Docker @('context','show')) -ne 'desktop-linux') { throw 'Contexto local inesperado.' }
$app = @(Docker @('inspect','app_php') | ConvertFrom-Json)[0]
$db = @(Docker @('inspect','app_db') | ConvertFrom-Json)[0]
if ($app.Config.Labels.'com.docker.compose.project' -ne 'docker' -or $db.State.Health.Status -ne 'healthy') { throw 'Stack local inesperada.' }
$codeMount = @($app.Mounts | Where-Object Destination -eq '/var/www/html')[0]
if ([IO.Path]::GetFullPath($codeMount.Source) -ne [IO.Path]::GetFullPath($workspace)) { throw 'Workspace do container inesperado.' }
$private = Split-Path (@($app.Mounts | Where-Object Destination -eq '/run/mail-center/secrets.json')[0].Source) -Parent
if (!(Test-Path -LiteralPath $private)) { throw 'Pasta privada ausente.' }
$backup = Join-Path ([IO.Path]::GetFullPath($BackupRoot)) ('php83-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
if ($backup.StartsWith([IO.Path]::GetFullPath($workspace) + '\', [StringComparison]::OrdinalIgnoreCase)) { throw 'Backup deve ficar fora do workspace.' }
New-Item -ItemType Directory -Path $backup -Force | Out-Null
$identity = [Security.Principal.WindowsIdentity]::GetCurrent().Name
& icacls.exe $backup /inheritance:r /grant:r "${identity}:(OI)(CI)F" | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'Falha ao proteger backup.' }
Docker @('inspect','app_php','app_db','app_pma') | Set-Content -LiteralPath (Join-Path $backup 'containers-private.json') -Encoding utf8
$composeFiles = $app.Config.Labels.'com.docker.compose.project.config_files'.Split(',')
foreach ($file in $composeFiles) { Copy-Item -LiteralPath $file -Destination (Join-Path $backup ([IO.Path]::GetFileName($file))) }
Copy-Item -LiteralPath (Join-Path $workspace 'docker/php/Dockerfile') -Destination (Join-Path $backup 'Dockerfile.before')
$tag = 'core-php-rollback:' + (Get-Date -Format 'yyyyMMddHHmmss')
Docker @('image','tag',$app.Image,$tag) | Out-Null
Write-Output 'Guardando imagem anterior...'
Docker @('image','save','--output',(Join-Path $backup 'php-before.tar'),$tag) | Out-Null
$dumpPath = '/tmp/core-php83-' + [Guid]::NewGuid().ToString('N') + '.sql'
try {
    $dumpCommand = 'umask 077; export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"; exec mysqldump -uroot --all-databases --single-transaction --quick --routines --events --triggers --hex-blob --set-gtid-purged=OFF --result-file=' + $dumpPath
    Docker @('exec','app_db','sh','-ec',$dumpCommand) | Out-Null
    Docker @('cp',"app_db:$dumpPath",(Join-Path $backup 'all-databases.sql')) | Out-Null
} finally { Docker @('exec','app_db','rm','-f',$dumpPath) | Out-Null }
Write-Output 'Guardando arquivos e configuracoes privadas...'
& tar.exe -czf (Join-Path $backup 'workspace.tar.gz') --exclude=workspace/.git --exclude=workspace/output --exclude=workspace/_deploy --exclude=workspace/storage/logs --exclude=workspace/storage/cache -C (Split-Path $workspace -Parent) (Split-Path $workspace -Leaf)
if ($LASTEXITCODE -ne 0) { throw 'Falha no backup dos arquivos.' }
& tar.exe -czf (Join-Path $backup 'mail-private.tar.gz') -C $private .
if ($LASTEXITCODE -ne 0) { throw 'Falha no backup privado.' }
foreach ($archive in @('workspace.tar.gz','mail-private.tar.gz')) {
    & tar.exe -tzf (Join-Path $backup $archive) > $null
    if ($LASTEXITCODE -ne 0) { throw "Arquivo invalido: $archive" }
}
$rootEnv = @($db.Config.Env | Where-Object { $_.StartsWith('MYSQL_ROOT_PASSWORD=') })
if ($rootEnv.Count -ne 1) { throw 'Credencial do banco ausente.' }
$rootEnv | Set-Content -LiteralPath (Join-Path $backup 'restore.env') -Encoding utf8
$restore = 'core-php83-restore-' + [Guid]::NewGuid().ToString('N').Substring(0,10)
Write-Output 'Validando restauracao em MySQL local isolado, sem portas...'
try {
    Docker @('run','-d','--name',$restore,'--network','none','--memory','768m','--env-file',(Join-Path $backup 'restore.env'),$db.Image,'--innodb-buffer-pool-size=64M','--performance-schema=OFF') | Out-Null
    $ready = $false
    for ($attempt = 0; $attempt -lt 90; $attempt++) {
        & $docker exec $restore sh -ec 'export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"; mysql -uroot -N -e "SELECT 1"' 2>$null | Out-Null
        if ($LASTEXITCODE -eq 0) { $ready = $true; break }
        Start-Sleep -Seconds 2
    }
    if (!$ready) { throw 'MySQL isolado nao ficou pronto.' }
    Docker @('cp',(Join-Path $backup 'all-databases.sql'),"${restore}:/tmp/restore.sql") | Out-Null
    Docker @('exec',$restore,'sh','-ec','export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"; mysql -uroot < /tmp/restore.sql') | Out-Null
    $query = 'SELECT TABLE_SCHEMA,COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA NOT IN (''mysql'',''sys'',''performance_schema'',''information_schema'') GROUP BY TABLE_SCHEMA ORDER BY TABLE_SCHEMA'
    $live = @(Docker @('exec','app_db','sh','-ec','export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"; exec mysql -uroot -N -e "$1"','sh',$query))
    $restored = @(Docker @('exec',$restore,'sh','-ec','export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"; exec mysql -uroot -N -e "$1"','sh',$query))
    if (($live -join "`n") -ne ($restored -join "`n")) { throw 'Inventario restaurado difere do original.' }
    $live | Set-Content -LiteralPath (Join-Path $backup 'database-inventory.txt') -Encoding utf8
} finally {
    # Exclusively the unique test container created above and its anonymous test volume.
    & $docker container inspect $restore > $null 2>&1
    if ($LASTEXITCODE -eq 0) { Docker @('rm','-f','-v',$restore) | Out-Null }
    Remove-Item -LiteralPath (Join-Path $backup 'restore.env') -ErrorAction SilentlyContinue
}
@{ Backup=$backup; ImageBefore=$app.Image; RollbackTag=$tag; DatabaseContainer=$db.Id; DatabaseImage=$db.Image; PrivateDirectory=$private; ComposeFiles=$composeFiles; RestoreVerified=$true } | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath (Join-Path $backup 'state.json') -Encoding utf8
$hashes = Get-ChildItem -LiteralPath $backup -File | Where-Object Name -ne 'SHA256SUMS' | Get-FileHash -Algorithm SHA256 | ForEach-Object { $_.Hash.ToLower() + '  ' + (Split-Path $_.Path -Leaf) }
$hashes | Set-Content -LiteralPath (Join-Path $backup 'SHA256SUMS') -Encoding utf8
Write-Output "BACKUP_VERIFIED=$backup"
