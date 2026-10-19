#requires -Version 7.0
param([string]$Config='')
$ErrorActionPreference='Stop'
if(!$Config){$Config=Join-Path $PSScriptRoot 'deploy.local.ps1'}
$taskSettings=. $Config;$taskServer=[string]$taskSettings.Server;$taskUser=[string]$taskSettings.User
if($taskServer -ne '157.230.214.72' -or $taskUser -notmatch '^[a-zA-Z0-9_-]+$'){throw 'Servidor diferente do confirmado.'}
$taskRoot=Split-Path $PSScriptRoot -Parent
$taskBundle=Join-Path $taskRoot '_deploy/mail-core-overlay.tar'
$taskHash=(Get-FileHash -LiteralPath $taskBundle -Algorithm SHA256).Hash.ToLowerInvariant()
$taskScript=[IO.File]::ReadAllText((Join-Path $PSScriptRoot 'central-contas-stage-release.sh')).Replace("`r`n","`n")
$taskEncoded=[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($taskScript))
$taskCommand=('bash -c "$(printf ''%s'' ''{0}'' | base64 -d)" -- {1}' -f $taskEncoded,$taskHash)
$taskInfo=[Diagnostics.ProcessStartInfo]::new('ssh.exe');$taskInfo.UseShellExecute=$false;$taskInfo.RedirectStandardInput=$true;$taskInfo.RedirectStandardOutput=$true
foreach($taskArg in @('-o','ConnectTimeout=15','-o','NumberOfPasswordPrompts=1','-o','PreferredAuthentications=password','-o','PubkeyAuthentication=no',"${taskUser}@${taskServer}",$taskCommand)){[void]$taskInfo.ArgumentList.Add($taskArg)}
Write-Host 'Prévia da release limitada à Central: somente pasta privada e comparação de arquivos. Digite a senha SSH. Sem publicação ou migração.' -ForegroundColor Cyan
$taskProcess=[Diagnostics.Process]::new();$taskProcess.StartInfo=$taskInfo
try{
    [void]$taskProcess.Start();$taskOutput=$taskProcess.StandardOutput.ReadToEndAsync()
    $taskStream=[IO.File]::OpenRead($taskBundle)
    try{$taskStream.CopyTo($taskProcess.StandardInput.BaseStream);$taskProcess.StandardInput.Close()}finally{$taskStream.Dispose()}
    $taskProcess.WaitForExit();$taskResult=$taskOutput.GetAwaiter().GetResult()
    [IO.File]::WriteAllText((Join-Path $taskRoot '_deploy/mail-overlay-server-plan.txt'),$taskResult,[Text.UTF8Encoding]::new($false))
    Write-Host $taskResult
    if($taskProcess.ExitCode -ne 0){throw 'Prévia não aprovada; revisar divergências antes de publicar.'}
}finally{$taskProcess.Dispose()}
