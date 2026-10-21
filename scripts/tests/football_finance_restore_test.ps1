#requires -Version 7.0
$ErrorActionPreference='Stop'
$taskRoot=Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
. (Join-Path $taskRoot 'scripts/futebol-financeiro-restaurar-local.ps1')
$taskDocker='C:\Program Files\Docker\Docker\resources\bin\docker.exe'
$taskDirectory=Join-Path $taskRoot ('_deploy/test-local-restore-'+[guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $taskDirectory | Out-Null
$taskInventory=@(@{path='projects/synthetic_team';tables=1;entries=2})
[IO.File]::WriteAllText((Join-Path $taskDirectory 'inventory.json'),(ConvertTo-Json -InputObject $taskInventory -Compress),[Text.UTF8Encoding]::new($false))
$taskSql="CREATE TABLE finance_entries(id INT PRIMARY KEY,title VARCHAR(100)); INSERT INTO finance_entries VALUES(1,'synthetic one'),(2,'synthetic two');"
$taskSource=[IO.File]::Create((Join-Path $taskDirectory 'project-0.sql.gz'));$taskGzip=[IO.Compression.GZipStream]::new($taskSource,[IO.Compression.CompressionLevel]::Optimal)
try{$taskBytes=[Text.Encoding]::UTF8.GetBytes($taskSql);$taskGzip.Write($taskBytes)}finally{$taskGzip.Dispose();$taskSource.Dispose()}
$taskBundle=Join-Path $taskDirectory 'bundle.tar'; & tar.exe -cf $taskBundle -C $taskDirectory inventory.json project-0.sql.gz
if($LASTEXITCODE -ne 0){throw 'Fixture packaging failed'}
$taskImage=& $taskDocker inspect --format '{{.Image}}' app_db
$taskDigest=(& $taskDocker image inspect --format '{{index .RepoDigests 0}}' $taskImage).Trim()
$taskHash=(Get-FileHash -LiteralPath $taskBundle -Algorithm SHA256).Hash.ToLowerInvariant()
$taskFailed=$false;try{Invoke-FinanceLocalRestore -Bundle $taskBundle -ExpectedHash ('0'*64) -Image $taskDigest}catch{$taskFailed=$true}
if(!$taskFailed){throw 'Hash mismatch was accepted'}
Invoke-FinanceLocalRestore -Bundle $taskBundle -ExpectedHash $taskHash -Image $taskDigest
$taskBad=Join-Path $taskDirectory 'bad';New-Item -ItemType Directory -Path $taskBad | Out-Null
[IO.File]::WriteAllText((Join-Path $taskBad 'unexpected.txt'),'synthetic')
$taskBadBundle=Join-Path $taskBad 'bundle.tar'; & tar.exe -cf $taskBadBundle -C $taskBad unexpected.txt
$taskBadHash=(Get-FileHash -LiteralPath $taskBadBundle -Algorithm SHA256).Hash.ToLowerInvariant();$taskFailed=$false
try{Invoke-FinanceLocalRestore -Bundle $taskBadBundle -ExpectedHash $taskBadHash -Image $taskDigest}catch{$taskFailed=$true}
if(!$taskFailed){throw 'Unexpected backup entry accepted'}
$taskShell=[IO.File]::ReadAllText((Join-Path $taskRoot 'scripts/futebol-financeiro-publicar.sh'))
if($taskShell.Contains('docker run -d') -or !$taskShell.Contains('RESTORE_OK $bundle_hash') -or !$taskShell.Contains('head -c "$2"')){throw 'Remote restoration safety invariant failed'}
Write-Output 'PASS: backup hash, isolated local MySQL restoration, counts, archive allowlist and no second database on production.'
