#requires -Version 7.0
param([Parameter(Mandatory=$true)][string]$PlanDirectory,[string]$Config='')
$ErrorActionPreference='Stop'
if($PlanDirectory -notmatch '^/opt/private-mail-center/releases/plan-[a-zA-Z0-9-]+$'){throw 'Plano privado inválido.'}
if(!$Config){$Config=Join-Path $PSScriptRoot 'deploy.local.ps1'}
$taskSettings=. $Config;$taskServer=[string]$taskSettings.Server;$taskUser=[string]$taskSettings.User
if($taskServer -ne '157.230.214.72' -or $taskUser -notmatch '^[a-zA-Z0-9_-]+$'){throw 'Servidor diferente do autorizado.'}
$taskRoot=Split-Path $PSScriptRoot -Parent
$taskScript=[IO.File]::ReadAllText((Join-Path $PSScriptRoot 'central-contas-aplicar-release.sh')).Replace("`r`n","`n")
$taskEncoded=[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($taskScript))
$taskCommand=('bash -c "$(printf ''%s'' ''{0}'' | base64 -d)" -- {1} --install-disabled' -f $taskEncoded,$PlanDirectory)
Write-Host 'Instalação autorizada da Central DESABILITADA, com backup e migrações próprias. Apenas o container web será recriado. Digite a senha SSH.' -ForegroundColor Cyan
& ssh.exe -o ConnectTimeout=15 -o ServerAliveInterval=15 -o ServerAliveCountMax=4 -o NumberOfPasswordPrompts=1 -o PreferredAuthentications=password -o PubkeyAuthentication=no "${taskUser}@${taskServer}" $taskCommand | Tee-Object -FilePath (Join-Path $taskRoot '_deploy/mail-core-installation.txt')
if($LASTEXITCODE -ne 0){throw 'Instalação não concluída; conferir relatório de reversão antes de repetir.'}
