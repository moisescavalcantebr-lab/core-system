param([string]$Config='')
$ErrorActionPreference='Stop'
if(!$Config){$Config=Join-Path $PSScriptRoot 'deploy.local.ps1'}
$taskSettings=. $Config
$taskServer=[string]$taskSettings.Server;$taskUser=if($taskSettings.User){[string]$taskSettings.User}else{'root'}
$taskRemotePath=[string]$taskSettings.RemotePath
if($taskServer -notmatch '^[a-zA-Z0-9.-]+$' -or $taskUser -notmatch '^[a-zA-Z0-9_-]+$' -or $taskRemotePath -notmatch '^/opt/[a-zA-Z0-9_/-]+$' -or $taskRemotePath -match '(^|/)\.\.(/|$)'){throw 'Destino de backup inválido.'}
$taskRoot=Split-Path $PSScriptRoot -Parent;$taskReport=Join-Path $taskRoot '_deploy/mail-server-backup.txt'
New-Item -ItemType Directory -Force -Path (Split-Path $taskReport -Parent) | Out-Null
$taskScript=[IO.File]::ReadAllText((Join-Path $PSScriptRoot 'central-contas-backup-servidor.sh'),[Text.Encoding]::UTF8)
$taskEncoded=[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($taskScript))
Write-Host "Backup preparatório em $taskServer. Digite a senha no SSH; ela não será salva. Não haverá deploy ou migração." -ForegroundColor Cyan
$taskCommand="printf '%s' '$taskEncoded' | base64 -d | bash -s -- '$taskRemotePath' '/opt/private-mail-center'"
& ssh.exe -o ConnectTimeout=15 -o NumberOfPasswordPrompts=1 -o PreferredAuthentications=password -o PubkeyAuthentication=no "${taskUser}@${taskServer}" $taskCommand | Tee-Object -FilePath $taskReport
if($LASTEXITCODE -ne 0){Write-Host 'Backup incompleto. Não publicar até conferir o problema.' -ForegroundColor Yellow;exit 1}
Write-Host "Relatório local salvo em $taskReport" -ForegroundColor Green
