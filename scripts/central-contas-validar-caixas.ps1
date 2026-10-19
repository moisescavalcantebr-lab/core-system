#requires -Version 7.0
param([string]$Config='',[switch]$StatusOnly)
$ErrorActionPreference='Stop'
if(!$Config){$Config=Join-Path $PSScriptRoot 'deploy.local.ps1'}
$taskSettings=. $Config;$taskServer=[string]$taskSettings.Server;$taskUser=[string]$taskSettings.User
if($taskServer -ne '157.230.214.72' -or $taskUser -notmatch '^[a-zA-Z0-9_-]+$'){throw 'Servidor diferente do confirmado.'}
$taskRoot=Split-Path $PSScriptRoot -Parent
$taskSource=[IO.File]::ReadAllText((Join-Path $PSScriptRoot 'central-contas-validar-caixas.php')).Replace("`r`n","`n")
$taskEncoded=[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($taskSource))
$taskMode=if($StatusOnly){'--status-only'}else{'--allow-provider-access'}
$taskReport=if($StatusOnly){'_deploy/mail-production-mailbox-diagnostics.txt'}else{'_deploy/mail-production-mailbox-validation.txt'}
$taskCommand=('printf ''%s'' ''{0}'' | base64 -d | docker exec -i --user www-data app_php php /dev/stdin {1}' -f $taskEncoded,$taskMode)
if($StatusOnly){Write-Host 'Diagnóstico somente leitura: estados técnicos e MIME sintético, sem acessar provedores. Digite a senha SSH.' -ForegroundColor Cyan}else{Write-Host 'Validação de Gmail/Zoho e um lote limitado de sincronização por caixa. Sem envio ou impressão de mensagens. Digite a senha SSH.' -ForegroundColor Cyan}
& ssh.exe -o ConnectTimeout=15 -o ServerAliveInterval=15 -o ServerAliveCountMax=4 -o NumberOfPasswordPrompts=1 -o PreferredAuthentications=password -o PubkeyAuthentication=no "${taskUser}@${taskServer}" $taskCommand | Tee-Object -FilePath (Join-Path $taskRoot $taskReport)
if($LASTEXITCODE -ne 0){throw 'Uma validação ficou pendente; confira o relatório e os estados das caixas no painel.'}
