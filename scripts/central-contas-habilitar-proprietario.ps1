#requires -Version 7.0
param([string]$Config='')
$ErrorActionPreference='Stop'
if(!$Config){$Config=Join-Path $PSScriptRoot 'deploy.local.ps1'}
$taskSettings=. $Config;$taskServer=[string]$taskSettings.Server;$taskUser=[string]$taskSettings.User
if($taskServer -ne '157.230.214.72' -or $taskUser -notmatch '^[a-zA-Z0-9_-]+$'){throw 'Servidor diferente do autorizado.'}
$taskRoot=Split-Path $PSScriptRoot -Parent
$taskScript=[IO.File]::ReadAllText((Join-Path $PSScriptRoot 'central-contas-habilitar-proprietario.sh')).Replace("`r`n","`n")
$taskEncoded=[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($taskScript))
$taskCommand=('bash -c "$(printf ''%s'' ''{0}'' | base64 -d)" -- --enable-reviewed-owner' -f $taskEncoded)
Write-Host 'Habilitação autorizada somente para o proprietário, com cadastros Gmail/Zoho e sem consentimento automático. Digite a senha SSH.' -ForegroundColor Cyan
& ssh.exe -o ConnectTimeout=15 -o ServerAliveInterval=15 -o ServerAliveCountMax=4 -o NumberOfPasswordPrompts=1 -o PreferredAuthentications=password -o PubkeyAuthentication=no "${taskUser}@${taskServer}" $taskCommand | Tee-Object -FilePath (Join-Path $taskRoot '_deploy/mail-owner-enablement.txt')
if($LASTEXITCODE -ne 0){throw 'Habilitação não concluída. Confira o relatório antes de repetir.'}
