#requires -Version 7.0
param([string]$Config='',[switch]$ValidateBatch)
$ErrorActionPreference='Stop'
if(!$Config){$Config=Join-Path $PSScriptRoot 'deploy.local.ps1'}
$taskSettings=. $Config;$taskServer=[string]$taskSettings.Server;$taskUser=[string]$taskSettings.User
if($taskServer -ne '157.230.214.72' -or $taskUser -notmatch '^[a-zA-Z0-9_-]+$'){throw 'Servidor diferente do confirmado.'}
$taskRoot=Split-Path $PSScriptRoot -Parent
$taskFile=if($ValidateBatch){'central-contas-validar-sync-gmail.php'}else{'central-contas-diagnosticar-gmail.php'}
$taskMode=if($ValidateBatch){'--sync-one-batch'}else{'--probe-read'}
$taskReport=if($ValidateBatch){'_deploy/mail-gmail-sync-batch-validation.txt'}else{'_deploy/mail-gmail-sync-diagnostic.txt'}
$taskSource=[IO.File]::ReadAllText((Join-Path $PSScriptRoot $taskFile)).Replace("`r`n","`n")
$taskEncoded=[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($taskSource))
$taskCommand=('printf ''%s'' ''{0}'' | base64 -d | docker exec -i --user www-data app_php php /dev/stdin {1}' -f $taskEncoded,$taskMode)
if($ValidateBatch){Write-Host 'Validar um lote Gmail de metadados com indicadores técnicos, sem alterar e-mails no provedor. Digite a senha SSH neste terminal.' -ForegroundColor Cyan}else{Write-Host 'Diagnóstico Gmail: estado da sincronização e consulta limitada de IDs, sem importar ou modificar mensagens. Digite a senha SSH neste terminal.' -ForegroundColor Cyan}
& ssh.exe -o ConnectTimeout=15 -o ServerAliveInterval=15 -o ServerAliveCountMax=4 -o NumberOfPasswordPrompts=1 -o PreferredAuthentications=password -o PubkeyAuthentication=no "${taskUser}@${taskServer}" $taskCommand | Tee-Object -FilePath (Join-Path $taskRoot $taskReport)
if($LASTEXITCODE -ne 0){throw 'Diagnóstico encontrou uma falha; confira somente os indicadores técnicos do relatório.'}
