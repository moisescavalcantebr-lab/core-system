#requires -Version 7.0
param([string]$Config='')
$ErrorActionPreference='Stop'
if(!$Config){$Config=Join-Path $PSScriptRoot 'deploy.local.ps1'}
$taskSettings=. $Config;$taskServer=[string]$taskSettings.Server;$taskUser=[string]$taskSettings.User
if($taskServer -ne '157.230.214.72' -or $taskUser -notmatch '^[a-zA-Z0-9_-]+$'){throw 'Servidor diferente do autorizado.'}
$taskRoot=Split-Path $PSScriptRoot -Parent
$taskPath='app/services/mail/MailSyncService.php'
$taskBefore=((& git -C $taskRoot show "625b2b2:$taskPath") -join "`n")+"`n"
if($LASTEXITCODE -ne 0){throw 'Baseline indisponível.'}
$taskAfter=[IO.File]::ReadAllText((Join-Path $taskRoot $taskPath)).Replace("`r`n","`n")
$taskBeforeHash=[Convert]::ToHexString([Security.Cryptography.SHA256]::HashData([Text.Encoding]::UTF8.GetBytes($taskBefore))).ToLowerInvariant()
$taskAfterBytes=[Text.Encoding]::UTF8.GetBytes($taskAfter)
$taskAfterHash=[Convert]::ToHexString([Security.Cryptography.SHA256]::HashData($taskAfterBytes)).ToLowerInvariant()
# Pin this authorized one-file release; later source edits require a new reviewed release.
if($taskAfterHash -ne 'e96fc8b86cf148ccfa2a35073824dc6e23cf642a421ab22da57e40e4c6bf4eae'){throw 'Fonte mudou após a revisão da correção.'}
$taskMemory=[IO.MemoryStream]::new()
$taskGzip=[IO.Compression.GZipStream]::new($taskMemory,[IO.Compression.CompressionLevel]::Optimal,$true)
$taskGzip.Write($taskAfterBytes,0,$taskAfterBytes.Length);$taskGzip.Dispose()
$taskPayload=[Convert]::ToBase64String($taskMemory.ToArray());$taskMemory.Dispose()
$taskScript=[IO.File]::ReadAllText((Join-Path $PSScriptRoot 'central-contas-publicar-correcao-sync.sh')).Replace("`r`n","`n").Replace('__BEFORE_HASH__',$taskBeforeHash).Replace('__AFTER_HASH__',$taskAfterHash).Replace('__SOURCE_GZIP_BASE64__',$taskPayload)
$taskValidationSource=[IO.File]::ReadAllText((Join-Path $PSScriptRoot 'central-contas-validar-sync-gmail.php')).Replace("`r`n","`n")
$taskScript=$taskScript.Replace('__GMAIL_VALIDATION_BASE64__',[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($taskValidationSource)))
Write-Host 'Publicação autorizada: somente correção da prioridade do Gmail e um lote de metadados, com backup. Digite a senha SSH no terminal.' -ForegroundColor Cyan
$taskScript | & ssh.exe -o ConnectTimeout=15 -o ServerAliveInterval=15 -o ServerAliveCountMax=4 -o NumberOfPasswordPrompts=1 -o PreferredAuthentications=password -o PubkeyAuthentication=no "${taskUser}@${taskServer}" 'bash -s -- --publish-gmail-sync-fix' | Tee-Object -FilePath (Join-Path $taskRoot '_deploy/mail-gmail-sync-fix-publication.txt')
if($LASTEXITCODE -ne 0){throw 'Publicação não concluída. Confira o relatório antes de repetir.'}
