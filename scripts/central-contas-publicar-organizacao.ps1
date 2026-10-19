#requires -Version 7.0
param([string]$Config='', [switch]$PackageOnly, [switch]$StatusOnly, [switch]$ReaderFix)
$ErrorActionPreference='Stop'
$taskRoot=Split-Path $PSScriptRoot -Parent
if(!$Config){$Config=Join-Path $PSScriptRoot 'deploy.local.ps1'}
$taskSettings=. $Config;$taskServer=[string]$taskSettings.Server;$taskUser=[string]$taskSettings.User
if($taskServer -ne '157.230.214.72' -or $taskUser -notmatch '^[a-zA-Z0-9_-]+$'){throw 'Servidor diferente do autorizado.'}
if($StatusOnly){
 if($PackageOnly){throw 'Escolha somente um modo de execução.'}
 $taskSource=[IO.File]::ReadAllText((Join-Path $PSScriptRoot 'central-contas-status-organizacao.php')).Replace("`r`n","`n")
 $taskEncoded=[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($taskSource))
 $taskCommand=('printf ''%s'' ''{0}'' | base64 -d | docker exec -i --user www-data app_php php /dev/stdin --status-only' -f $taskEncoded)
 Write-Host 'Conferência de permissões e contagens em produção, sem chamadas aos provedores ou alterações. Digite a senha SSH somente neste terminal.' -ForegroundColor Cyan
 & ssh.exe -o ConnectTimeout=15 -o ServerAliveInterval=15 -o ServerAliveCountMax=4 -o NumberOfPasswordPrompts=1 -o PreferredAuthentications=password -o PubkeyAuthentication=no "${taskUser}@${taskServer}" $taskCommand | Tee-Object -FilePath (Join-Path $taskRoot '_deploy/mail-organization-status.txt')
 if($LASTEXITCODE -ne 0){throw 'Conferência não concluída; veja o relatório técnico.'}
 exit 0
}
$taskBaseline=Join-Path $taskRoot '_deploy/mail-core-overlay-14b3bfd2623c4c96b31fb4c28b52505c'
if(!(Test-Path -LiteralPath (Join-Path $taskBaseline 'mail-overlay.json'))){throw 'Pacote original necessário para conferir baseline.'}
$taskPaths=@(
 'app/services/mail/MailMutableProviderInterface.php','app/services/mail/MailTrashReconciler.php',
 'app/services/mail/MailHttpClient.php','app/services/mail/MailAuditService.php','app/services/mail/MailMessageService.php',
 'app/services/mail/MailRemoteMessageService.php','app/services/mail/MailOAuthService.php',
 'app/services/mail/providers/GmailMailProvider.php','app/services/mail/providers/ZohoMailProvider.php','app/services/mail/MailSyncService.php',
 'web/admin/central_contas/_message_organization.php','web/admin/central_contas/_connection.php','web/admin/central_contas/_inbox.php',
 'web/admin/central_contas/_message_content.php','web/assets/css/admin/central_contas.css','web/assets/js/central_contas.js',
 'web/central-email/privacidade.html','web/central-email/termos.html','app/helpers/mail_pilot.php','app/actions/central_contas/message_update.php'
)
if($ReaderFix){$taskPaths=@('web/assets/js/central_contas.js','app/actions/central_contas/message_update.php')}
$taskStage=Join-Path $taskRoot ('_deploy/mail-organization-'+[Guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $taskStage | Out-Null
function Get-TaskNormalizedHash([string]$Text){[Convert]::ToHexString([Security.Cryptography.SHA256]::HashData([Text.Encoding]::UTF8.GetBytes($Text.Replace("`r`n","`n")))).ToLowerInvariant()}
$taskEntries=@()
foreach($taskPath in $taskPaths){
 $taskText=[IO.File]::ReadAllText((Join-Path $taskRoot $taskPath)).Replace("`r`n","`n")
 $taskHash=Get-TaskNormalizedHash $taskText;$taskAllowed=@($taskHash);$taskOld=Join-Path $taskBaseline $taskPath
 if(Test-Path -LiteralPath $taskOld -PathType Leaf){$taskAllowed+=Get-TaskNormalizedHash ([IO.File]::ReadAllText($taskOld))}
 if($ReaderFix){$taskGitText=((& git -C $taskRoot show "899ab3d:$taskPath") -join "`n")+"`n";if($LASTEXITCODE -ne 0){throw 'Baseline de produção ausente'};$taskAllowed+=Get-TaskNormalizedHash $taskGitText}
 if($taskPath -eq 'app/services/mail/MailMessageService.php'){$taskGitText=((& git -C $taskRoot show "c031f82:$taskPath") -join "`n")+"`n";if($LASTEXITCODE -ne 0){throw 'Baseline do filtro ausente'};$taskAllowed+=Get-TaskNormalizedHash $taskGitText}
 if($taskPath -eq 'app/services/mail/MailSyncService.php'){$taskGitText=((& git -C $taskRoot show "0994076:$taskPath") -join "`n")+"`n";if($LASTEXITCODE -ne 0){throw 'Baseline do Gmail ausente'};$taskAllowed+=Get-TaskNormalizedHash $taskGitText}
 $taskDestination=Join-Path $taskStage $taskPath;New-Item -ItemType Directory -Force -Path (Split-Path $taskDestination -Parent) | Out-Null
 [IO.File]::WriteAllText($taskDestination,$taskText,[Text.UTF8Encoding]::new($false))
 $taskEntries+=@{path=$taskPath;sha256=$taskHash;allowed=@($taskAllowed|Sort-Object -Unique);allow_missing=!(Test-Path -LiteralPath $taskOld -PathType Leaf)}
}
$taskManifest=@{schema=1;mode=$(if($ReaderFix){'reader-fix'}else{'organization'});files=$taskEntries}
[IO.File]::WriteAllText((Join-Path $taskStage 'organization-manifest.json'),($taskManifest|ConvertTo-Json -Depth 6),[Text.UTF8Encoding]::new($false))
Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'central-contas-status-organizacao.php') -Destination (Join-Path $taskStage 'status.php')
Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'central-contas-conferir-organizacao.php') -Destination (Join-Path $taskStage 'check.php')
$taskArchive=$taskStage+'.tar'
& tar.exe -cf $taskArchive -C $taskStage .
if($LASTEXITCODE -ne 0){throw 'Empacotamento falhou.'}
$taskHash=(Get-FileHash -LiteralPath $taskArchive -Algorithm SHA256).Hash.ToLowerInvariant()
Write-Host "Pacote limitado à Central: $($taskPaths.Count) arquivos; nenhuma migração, ENV local, token ou dependência incluídos."
if($PackageOnly){Write-Output "package_sha256=$taskHash";exit 0}
$taskScript=[IO.File]::ReadAllText((Join-Path $PSScriptRoot 'central-contas-publicar-organizacao.sh')).Replace("`r`n","`n")
$taskEncoded=[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($taskScript))
$taskCommand=('bash -c "$(printf ''%s'' ''{0}'' | base64 -d)" -- {1} --publish-organization' -f $taskEncoded,$taskHash)
if($ReaderFix){$taskCommand+=' --reader-fix'}
$taskInfo=[Diagnostics.ProcessStartInfo]::new('ssh.exe');$taskInfo.UseShellExecute=$false;$taskInfo.RedirectStandardInput=$true;$taskInfo.RedirectStandardOutput=$true;$taskInfo.RedirectStandardError=$true
foreach($taskArg in @('-o','ConnectTimeout=15','-o','ServerAliveInterval=15','-o','ServerAliveCountMax=4','-o','NumberOfPasswordPrompts=1','-o','PreferredAuthentications=password','-o','PubkeyAuthentication=no',"${taskUser}@${taskServer}",$taskCommand)){[void]$taskInfo.ArgumentList.Add($taskArg)}
if($ReaderFix){Write-Host 'Digite a senha SSH somente neste terminal. Correção de leitura: dois arquivos, backup e reversão, sem sincronizar ou alterar mensagens.' -ForegroundColor Cyan}else{Write-Host 'Digite a senha SSH somente neste terminal. Publicação da Central com backup/reversão e até cinco lotes por caixa; sem envio ou exclusão de e-mails.' -ForegroundColor Cyan}
$taskProcess=[Diagnostics.Process]::new();$taskProcess.StartInfo=$taskInfo
try{
 [void]$taskProcess.Start();$taskErrors=$taskProcess.StandardError.ReadToEndAsync();$taskStream=[IO.File]::OpenRead($taskArchive)
 try{$taskStream.CopyTo($taskProcess.StandardInput.BaseStream);$taskProcess.StandardInput.Close()}finally{$taskStream.Dispose()}
 $taskReport=Join-Path $taskRoot $(if($ReaderFix){'_deploy/mail-reader-fix-publication.txt'}else{'_deploy/mail-organization-publication.txt'});[IO.File]::WriteAllText($taskReport,'',[Text.UTF8Encoding]::new($false))
 while($null -ne ($taskLine=$taskProcess.StandardOutput.ReadLine())){[IO.File]::AppendAllText($taskReport,$taskLine+"`n",[Text.UTF8Encoding]::new($false));Write-Host $taskLine}
 $taskProcess.WaitForExit()
 $taskErrorText=$taskErrors.GetAwaiter().GetResult();if($taskErrorText){[IO.File]::AppendAllText($taskReport,$taskErrorText,[Text.UTF8Encoding]::new($false));Write-Host $taskErrorText}
 if($taskProcess.ExitCode -ne 0){throw 'Publicação não concluída. Confira o relatório antes de repetir.'}
}finally{$taskProcess.Dispose()}
