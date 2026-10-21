# Dot-source helper. Restores only into a disposable, network-isolated local container.
function Invoke-FinanceLocalRestore {
 param([Parameter(Mandatory)][string]$Bundle,[Parameter(Mandatory)][string]$ExpectedHash,[Parameter(Mandatory)][string]$Image)
 if($ExpectedHash -notmatch '^[a-f0-9]{64}$' -or $Image -notmatch '^mysql@sha256:[a-f0-9]{64}$'){throw 'Metadados de restauracao invalidos.'}
 if((Get-FileHash -LiteralPath $Bundle -Algorithm SHA256).Hash.ToLowerInvariant() -ne $ExpectedHash){throw 'Hash do backup recebido divergiu.'}
 $restoreDirectory=Split-Path $Bundle -Parent
 $restoreDocker='C:\Program Files\Docker\Docker\resources\bin\docker.exe'
 function Invoke-FinanceDocker([string[]]$NativeArguments){
  $oldPreference=$ErrorActionPreference;$ErrorActionPreference='Continue'
  try{$output=& $restoreDocker @NativeArguments 2>&1;$code=$LASTEXITCODE}finally{$ErrorActionPreference=$oldPreference}
  if($code -ne 0){throw 'Falha no Docker da validacao local; dados privados omitidos.'}
  return ($output -join "`n")
 }
 $memory=[long](Invoke-FinanceDocker @('info','--format','{{.MemTotal}}'))
 if($memory -lt 1610612736){throw 'O Docker local precisa de pelo menos 1,5 GB de memoria para este teste.'}
 $archiveStream=[IO.File]::OpenRead($Bundle);$reader=[System.Formats.Tar.TarReader]::new($archiveStream);$seen=@{}
 try{
  while($null -ne ($entry=$reader.GetNextEntry())){
   if($entry.Name -notmatch '^(inventory\.json|project-[0-9]+\.sql\.gz)$' -or $entry.EntryType.ToString() -notin @('RegularFile','V7RegularFile') -or $seen.ContainsKey($entry.Name)){throw 'Conteudo inesperado no backup.'}
   if($entry.Length -gt 104857600){throw 'Backup excede o limite desta validacao.'}
   $destination=[IO.File]::Create((Join-Path $restoreDirectory $entry.Name));try{$entry.DataStream.CopyTo($destination)}finally{$destination.Dispose()};$seen[$entry.Name]=$true
  }
 }finally{$reader.Dispose();$archiveStream.Dispose()}
 $inventory=@(Get-Content -LiteralPath (Join-Path $restoreDirectory 'inventory.json') -Raw | ConvertFrom-Json)
 if(!$inventory.Count -or $seen.Count -ne ($inventory.Count+1)){throw 'Inventario do backup invalido.'}
 $oldPreference=$ErrorActionPreference;$ErrorActionPreference='Continue'
 try{& $restoreDocker image inspect $Image --format '{{.Id}}' *> $null;$hasImage=$LASTEXITCODE -eq 0}finally{$ErrorActionPreference=$oldPreference}
 if(!$hasImage){Write-Host 'Obtendo a mesma imagem oficial do MySQL usada pelo servidor...';$null=Invoke-FinanceDocker @('pull',$Image)}
 $restoreName='ff-local-restore-'+[guid]::NewGuid().ToString('N');$created=$false
 try{
  $null=Invoke-FinanceDocker @('run','-d','--network','none','--memory','768m','--memory-swap','1g','--cpus','1','--pids-limit','256','--name',$restoreName,'-e','MYSQL_ALLOW_EMPTY_PASSWORD=yes',$Image,'--skip-log-bin','--event-scheduler=OFF','--performance-schema=OFF','--innodb-buffer-pool-size=64M');$created=$true
  $ready=$false;$deadline=[DateTime]::UtcNow.AddSeconds(120)
  while([DateTime]::UtcNow -lt $deadline){
   $oldPreference=$ErrorActionPreference;$ErrorActionPreference='Continue'
   try{& $restoreDocker exec $restoreName mysqladmin '--host=127.0.0.1' '--connect-timeout=2' '-uroot' ping --silent *> $null;$ready=$LASTEXITCODE -eq 0}finally{$ErrorActionPreference=$oldPreference}
   if($ready){break};Start-Sleep -Seconds 1
  }
  if(!$ready){throw 'O banco temporario local nao ficou disponivel.'}
  for($index=0;$index -lt $inventory.Count;$index++){
   $project=$inventory[$index]
   if([string]$project.path -notmatch '^projects/[a-zA-Z0-9_-]+$' -or [string]$project.tables -notmatch '^[0-9]+$' -or [string]$project.entries -notmatch '^[0-9]+$'){throw 'Contagens do backup invalidas.'}
   $null=Invoke-FinanceDocker @('exec',$restoreName,'mysql','-uroot','-e','DROP DATABASE IF EXISTS restore_test; CREATE DATABASE restore_test CHARACTER SET utf8mb4')
   $info=[Diagnostics.ProcessStartInfo]::new($restoreDocker);$info.UseShellExecute=$false;$info.RedirectStandardInput=$true;$info.RedirectStandardOutput=$true;$info.RedirectStandardError=$true
   foreach($arg in @('exec','-i',$restoreName,'mysql','-uroot','restore_test')){[void]$info.ArgumentList.Add($arg)}
   $process=[Diagnostics.Process]::new();$process.StartInfo=$info
   $source=[IO.File]::OpenRead((Join-Path $restoreDirectory "project-$index.sql.gz"));$gzip=[IO.Compression.GZipStream]::new($source,[IO.Compression.CompressionMode]::Decompress)
   try{
    [void]$process.Start();$stdout=$process.StandardOutput.ReadToEndAsync();$stderr=$process.StandardError.ReadToEndAsync();$gzip.CopyTo($process.StandardInput.BaseStream);$process.StandardInput.Close()
    if(!$process.WaitForExit(120000)){$process.Kill($true);throw 'A restauracao local ultrapassou o tempo limite.'}
    if($process.ExitCode -ne 0){throw 'A restauracao do backup falhou. Nenhuma migracao deve ser aplicada.'}
   }finally{$gzip.Dispose();$source.Dispose();$process.Dispose()}
   $tables=Invoke-FinanceDocker @('exec',$restoreName,'mysql','-uroot','-N','-e',"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='restore_test' AND table_type='BASE TABLE'")
   $entries=Invoke-FinanceDocker @('exec',$restoreName,'mysql','-uroot','-N','-e','SELECT COUNT(*) FROM restore_test.finance_entries')
   if([int]$tables -ne [int]$project.tables -or [int]$entries -ne [int]$project.entries){throw 'Contagens da restauracao local divergem do servidor.'}
   Write-Host "finance_backup_restore_$index=PASS (computador local)" -ForegroundColor Green
  }
 }finally{if($created){$null=Invoke-FinanceDocker @('rm','-fv',$restoreName)}}
}
