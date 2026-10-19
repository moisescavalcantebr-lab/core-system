#requires -Version 7.0
param([string]$Config='')
$ErrorActionPreference='Stop'
if(!$Config){$Config=Join-Path $PSScriptRoot 'deploy.local.ps1'}
$taskSettings=. $Config;$taskServer=[string]$taskSettings.Server;$taskUser=[string]$taskSettings.User
if($taskServer -ne '157.230.214.72' -or $taskUser -notmatch '^[a-zA-Z0-9_-]+$'){throw 'Servidor diferente do confirmado.'}
$taskRoot=Split-Path $PSScriptRoot -Parent
$taskVerify=[IO.File]::ReadAllText((Join-Path $PSScriptRoot 'central-contas-verificar-edge.sh')).Replace("`r`n","`n")
$taskBackup=[IO.File]::ReadAllText((Join-Path $PSScriptRoot 'central-contas-backup-servidor.sh')).Replace("`r`n","`n")
$taskVerifyEncoded=[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($taskVerify))
$taskBackupEncoded=[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($taskBackup))
$taskCommand=('set -e; printf ''%s'' ''{0}'' | base64 -d | bash -s; printf ''%s'' ''{1}'' | base64 -d | bash -s -- /opt/workspace /opt/private-mail-center' -f $taskVerifyEncoded,$taskBackupEncoded)
Write-Host 'Conferência de cookies/logs com marcadores sintéticos e backup protegido atualizado. Digite a senha SSH. Sem deploy ou envios de e-mail.' -ForegroundColor Cyan
& ssh.exe -o ConnectTimeout=15 -o ServerAliveInterval=15 -o ServerAliveCountMax=4 -o NumberOfPasswordPrompts=1 -o PreferredAuthentications=password -o PubkeyAuthentication=no "${taskUser}@${taskServer}" $taskCommand | Tee-Object -FilePath (Join-Path $taskRoot '_deploy/mail-server-post-https.txt')
if($LASTEXITCODE -ne 0){throw 'Conferência ou backup incompleto; não habilitar a Central.'}
