param([string]$Config='')
$ErrorActionPreference='Stop'
if(!$Config){$Config=Join-Path $PSScriptRoot 'deploy.local.ps1'}
$taskSettings=. $Config;$taskServer=[string]$taskSettings.Server;$taskUser=[string]$taskSettings.User
if($taskServer -ne '157.230.214.72' -or $taskUser -notmatch '^[a-zA-Z0-9_-]+$'){throw 'Destino SSH diferente do servidor confirmado.'}
$taskRoot=Split-Path $PSScriptRoot -Parent
$taskScript=[IO.File]::ReadAllText((Join-Path $PSScriptRoot 'central-contas-inspecionar-tls.sh'),[Text.Encoding]::UTF8).Replace("`r`n","`n")
$taskEncoded=[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($taskScript))
$taskCommand="printf '%s' '$taskEncoded' | base64 -d | bash -s"
Write-Host 'Inspeção TLS SOMENTE LEITURA. Digite a senha diretamente no SSH.' -ForegroundColor Cyan
& ssh.exe -o ConnectTimeout=15 -o NumberOfPasswordPrompts=1 -o PreferredAuthentications=password -o PubkeyAuthentication=no "${taskUser}@${taskServer}" $taskCommand | Tee-Object -FilePath (Join-Path $taskRoot '_deploy/mail-server-tls-preflight.txt')
if($LASTEXITCODE -ne 0){throw 'Inspeção incompleta; nenhuma configuração alterada.'}
