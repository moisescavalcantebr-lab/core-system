#requires -Version 7.0
param([string]$Config='')
$ErrorActionPreference='Stop'
if(!$Config){$Config=Join-Path $PSScriptRoot 'deploy.local.ps1'}
$taskSettings=. $Config;$taskServer=[string]$taskSettings.Server;$taskUser=[string]$taskSettings.User
if($taskServer -ne '157.230.214.72' -or $taskUser -notmatch '^[a-zA-Z0-9_-]+$'){throw 'Destino SSH diferente do servidor autorizado.'}
$taskRoot=Split-Path $PSScriptRoot -Parent
$taskScript=[IO.File]::ReadAllText((Join-Path $PSScriptRoot 'central-contas-ativar-tls.sh')).Replace("`r`n","`n")
$taskEncoded=[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($taskScript))
$taskCommand=('bash -c "$(printf ''%s'' ''{0}'' | base64 -d)" -- --activate-origin' -f $taskEncoded)
Write-Host 'Ativação HTTPS autorizada: será recriado somente o serviço web, com reversão em caso de falha. Digite a senha SSH.' -ForegroundColor Cyan
& ssh.exe -o ConnectTimeout=15 -o ServerAliveInterval=15 -o ServerAliveCountMax=4 -o NumberOfPasswordPrompts=1 -o PreferredAuthentications=password -o PubkeyAuthentication=no "${taskUser}@${taskServer}" $taskCommand | Tee-Object -FilePath (Join-Path $taskRoot '_deploy/mail-server-tls-activation.txt')
if($LASTEXITCODE -ne 0){throw 'Ativação não concluída. Confira o relatório de reversão antes de repetir.'}
