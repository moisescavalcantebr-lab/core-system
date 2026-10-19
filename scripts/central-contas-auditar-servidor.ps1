param([string]$Config='')
$ErrorActionPreference='Stop'
if(!$Config){$Config=Join-Path $PSScriptRoot 'deploy.local.ps1'}
$taskSettings=. $Config
$taskServer=[string]$taskSettings.Server
$taskUser=if($taskSettings.User){[string]$taskSettings.User}else{'root'}
if($taskServer -notmatch '^[a-zA-Z0-9.-]+$' -or $taskUser -notmatch '^[a-zA-Z0-9_-]+$'){throw 'Destino SSH inválido.'}
$taskRoot=Split-Path $PSScriptRoot -Parent
$taskReport=Join-Path $taskRoot '_deploy/mail-server-inspection.txt'
New-Item -ItemType Directory -Force -Path (Split-Path $taskReport -Parent) | Out-Null
Write-Host "Inspeção SOMENTE LEITURA em $taskServer. Digite a senha diretamente no SSH; ela não será salva." -ForegroundColor Cyan
$taskProbe=[IO.File]::ReadAllText((Join-Path $taskRoot 'app/console/inspect_mail_server.php'),[Text.Encoding]::UTF8)
$taskEncoded=[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($taskProbe))
$taskRemoteCommand="printf '%s' '$taskEncoded' | base64 -d | docker exec -i --user www-data app_php php"
& ssh.exe -o ConnectTimeout=15 -o NumberOfPasswordPrompts=1 -o PreferredAuthentications=password -o PubkeyAuthentication=no "${taskUser}@${taskServer}" $taskRemoteCommand | Tee-Object -FilePath $taskReport
if($LASTEXITCODE -ne 0){Write-Host 'Inspeção não concluída. Nenhum arquivo foi enviado nem alterado no servidor.' -ForegroundColor Yellow;exit 1}
Write-Host "Relatório local salvo em $taskReport" -ForegroundColor Green
