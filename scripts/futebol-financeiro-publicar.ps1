#requires -Version 7.0
param([switch]$PackageOnly)
$ErrorActionPreference='Stop'
$taskRoot=Split-Path $PSScriptRoot -Parent
. (Join-Path $PSScriptRoot 'futebol-financeiro-restaurar-local.ps1')
$taskSettings=. (Join-Path $PSScriptRoot 'deploy.local.ps1')
$taskServer=[string]$taskSettings.Server;$taskUser=[string]$taskSettings.User
if($taskServer -ne '157.230.214.72' -or $taskUser -ne 'root'){throw 'Servidor diferente do autorizado.'}
$taskPaths=@(& git -C $taskRoot diff --name-only 73d9bba HEAD -- modules/financeiro modules/financeiro_futebol bases/futebol/modules/financeiro bases/futebol/modules/financeiro_futebol bases/futebol/web/admin/financeiro bases/futebol/web/admin/player/financeiro.php bases/futebol/app/database/schema.sql bases/financeiro/modules/financeiro bases/financeiro/web/admin/financeiro)
if($LASTEXITCODE -ne 0 -or $taskPaths.Count -lt 30){throw 'Lista de arquivos inesperada.'}
$taskRevision='HEAD';$taskBeforeRevision='73d9bba'
$taskStage=Join-Path $taskRoot ('_deploy/futebol-financeiro-release-'+[Guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $taskStage | Out-Null
function Get-TaskHash([string]$Text){[Convert]::ToHexString([Security.Cryptography.SHA256]::HashData([Text.Encoding]::UTF8.GetBytes($Text.Replace("`r`n","`n")))).ToLowerInvariant()}
$taskEntries=@()
foreach($taskPath in $taskPaths){
 $taskText=[IO.File]::ReadAllText((Join-Path $taskRoot $taskPath)).Replace("`r`n","`n")
 $taskHash=Get-TaskHash $taskText
 $taskCommitted=((& git -C $taskRoot show "${taskRevision}:$taskPath") -join "`n")+"`n"
 if($LASTEXITCODE -ne 0 -or (Get-TaskHash $taskCommitted) -ne $taskHash){throw "Arquivo fora da versão revisada: $taskPath"}
 $taskHasBefore=(& git -C $taskRoot ls-tree $taskBeforeRevision -- $taskPath)
 $taskAllowed=@($taskHash)
 if($taskHasBefore){$taskBefore=((& git -C $taskRoot show "${taskBeforeRevision}:$taskPath") -join "`n")+"`n";if($LASTEXITCODE -ne 0){throw 'Baseline inválido'};$taskAllowed+=Get-TaskHash $taskBefore}
 $taskDestination=Join-Path $taskStage $taskPath
 New-Item -ItemType Directory -Force -Path (Split-Path $taskDestination -Parent) | Out-Null
 [IO.File]::WriteAllText($taskDestination,$taskText,[Text.UTF8Encoding]::new($false))
 $taskEntries+=@{path=$taskPath;sha256=$taskHash;allowed=@($taskAllowed|Sort-Object -Unique);allow_missing=!$taskHasBefore}
}
[IO.File]::WriteAllText((Join-Path $taskStage 'manifest.json'),(@{files=$taskEntries;mode='finance'}|ConvertTo-Json -Depth 6),[Text.UTF8Encoding]::new($false))
Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'futebol-financeiro-conferir.php') -Destination (Join-Path $taskStage 'check.php')
Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'futebol-financeiro-migrar.php') -Destination (Join-Path $taskStage 'migrate.php')
$taskArchive=$taskStage+'.tar'
& tar.exe -cf $taskArchive -C $taskStage .
if($LASTEXITCODE -ne 0){throw 'Empacotamento falhou.'}
$taskHash=(Get-FileHash -LiteralPath $taskArchive -Algorithm SHA256).Hash.ToLowerInvariant()
Write-Host "Pacote Futebol: $($taskPaths.Count) arquivos revisados e cópias correspondentes nos projetos instalados."
if($PackageOnly){Write-Output "package_sha256=$taskHash";exit 0}
$taskScript=[IO.File]::ReadAllText((Join-Path $PSScriptRoot 'futebol-financeiro-publicar.sh')).Replace("`r`n","`n")
$taskEncoded=[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($taskScript))
$taskSize=(Get-Item -LiteralPath $taskArchive).Length
$taskCommand=('bash -c "$(printf ''%s'' ''{0}'' | base64 -d)" -- {1} {2}' -f $taskEncoded,$taskHash,$taskSize)

$taskInfo=[Diagnostics.ProcessStartInfo]::new('ssh.exe');$taskInfo.UseShellExecute=$false;$taskInfo.RedirectStandardInput=$true;$taskInfo.RedirectStandardOutput=$true;$taskInfo.RedirectStandardError=$true;$taskInfo.StandardInputEncoding=[Text.UTF8Encoding]::new($false)
foreach($taskArg in @('-o','ConnectTimeout=15','-o','ServerAliveInterval=15','-o','ServerAliveCountMax=4','-o','NumberOfPasswordPrompts=1','-o','PreferredAuthentications=password','-o','PubkeyAuthentication=no',"${taskUser}@${taskServer}",$taskCommand)){[void]$taskInfo.ArgumentList.Add($taskArg)}
Write-Host 'Digite a senha SSH somente neste terminal. Publicacao do Financeiro do Time: backup do projeto, teste de restauracao e migracao aditiva. E-mails e configuracoes serao preservados.' -ForegroundColor Cyan
$taskProcess=[Diagnostics.Process]::new();$taskProcess.StartInfo=$taskInfo
$taskStarted=$false;$taskErrorLogged=$false
$taskReport=Join-Path $taskRoot '_deploy/futebol-financeiro-publication.txt';[IO.File]::WriteAllText($taskReport,'',[Text.UTF8Encoding]::new($false))
try{
 [void]$taskProcess.Start();$taskStarted=$true;$taskErrors=$taskProcess.StandardError.ReadToEndAsync();$taskStream=[IO.File]::OpenRead($taskArchive)
 try{$taskStream.CopyTo($taskProcess.StandardInput.BaseStream);$taskProcess.StandardInput.BaseStream.Flush();$taskProcess.StandardInput.NewLine="`n"}finally{$taskStream.Dispose()}
 $taskBundleHash='';$taskDatabaseImage='';$taskValidated=$false;$taskPrivate=''
 while($null -ne ($taskLine=$taskProcess.StandardOutput.ReadLine())){
  if($taskLine.StartsWith('FINANCE_IMAGE=')){$taskDatabaseImage=$taskLine.Substring(14);continue}
  if($taskLine.StartsWith('FINANCE_BUNDLE_HASH=')){$taskBundleHash=$taskLine.Substring(20);continue}
  if($taskLine.StartsWith('FINANCE_BUNDLE=')){
   if($taskValidated -or $taskBundleHash -notmatch '^[a-f0-9]{64}$' -or $taskDatabaseImage -notmatch '^mysql@sha256:[a-f0-9]{64}$'){throw 'Protocolo do backup invalido.'}
   $taskPrivateRoot=Join-Path (Split-Path $taskRoot -Parent) 'private-futebol'
   $taskPrivate=Join-Path $taskPrivateRoot ('restore-'+[guid]::NewGuid().ToString('N'))
   New-Item -ItemType Directory -Path $taskPrivate -Force | Out-Null
   $taskAcl=[Security.AccessControl.DirectorySecurity]::new();$taskAcl.SetAccessRuleProtection($true,$false)
   $taskSid=[Security.Principal.WindowsIdentity]::GetCurrent().User
   $taskAcl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new($taskSid,'FullControl','ContainerInherit,ObjectInherit','None','Allow'))
   $taskAcl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new([Security.Principal.SecurityIdentifier]::new('S-1-5-18'),'FullControl','ContainerInherit,ObjectInherit','None','Allow'))
   Set-Acl -LiteralPath $taskPrivate -AclObject $taskAcl
   if($taskLine.Length -gt 139810160){throw 'Backup recebido excede o limite.'}
   $taskBundle=Join-Path $taskPrivate 'restore-bundle.tar';[IO.File]::WriteAllBytes($taskBundle,[Convert]::FromBase64String($taskLine.Substring(15)))
   Write-Host 'Backup recebido em pasta privada. Validando no Docker local...' -ForegroundColor Cyan
   Invoke-FinanceLocalRestore -Bundle $taskBundle -ExpectedHash $taskBundleHash -Image $taskDatabaseImage
   $taskValidated=$true;$taskProcess.StandardInput.WriteLine('RESTORE_OK '+$taskBundleHash);$taskProcess.StandardInput.Flush();$taskProcess.StandardInput.Close()
   [IO.File]::AppendAllText($taskReport,"finance_client_restore=PASS`n",[Text.UTF8Encoding]::new($false));continue
  }
  [IO.File]::AppendAllText($taskReport,$taskLine+"`n",[Text.UTF8Encoding]::new($false));Write-Host $taskLine
 }
 $taskProcess.WaitForExit()
 $taskErrorText=$taskErrors.GetAwaiter().GetResult();$taskErrorLogged=$true;if($taskErrorText){[IO.File]::AppendAllText($taskReport,$taskErrorText,[Text.UTF8Encoding]::new($false));Write-Host $taskErrorText}
 if($taskProcess.ExitCode -ne 0 -or !$taskValidated){throw 'Publicacao nao concluida. Confira o relatorio antes de repetir.'}
 if($taskPrivate){
  $taskResolved=[IO.Path]::GetFullPath($taskPrivate);$taskBound=[IO.Path]::GetFullPath($taskPrivateRoot).TrimEnd('\')+'\'
  if(!$taskResolved.StartsWith($taskBound,[StringComparison]::OrdinalIgnoreCase) -or (Split-Path $taskResolved -Leaf) -notmatch '^restore-[a-f0-9]{32}$'){throw 'Diretorio privado inesperado.'}
  Remove-Item -LiteralPath $taskResolved -Recurse -Force
 }
}finally{
 if($taskStarted -and !$taskProcess.HasExited){$taskProcess.StandardInput.Close();if(!$taskProcess.WaitForExit(30000)){$taskProcess.Kill($true)}}
 if($taskStarted -and !$taskErrorLogged -and $taskProcess.HasExited){$taskErrorText=$taskErrors.GetAwaiter().GetResult();if($taskErrorText){[IO.File]::AppendAllText($taskReport,$taskErrorText,[Text.UTF8Encoding]::new($false));Write-Host $taskErrorText}}
 $taskProcess.Dispose()
}
