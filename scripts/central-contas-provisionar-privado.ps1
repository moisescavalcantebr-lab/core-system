#requires -Version 7.0
param(
    [Parameter(Mandatory=$true)][string]$SecretsFile,
    [string]$Config=''
)
$ErrorActionPreference='Stop'
if(!$Config){$Config=Join-Path $PSScriptRoot 'deploy.local.ps1'}
$taskSettings=. $Config;$taskServer=[string]$taskSettings.Server;$taskUser=[string]$taskSettings.User
if($taskServer -notmatch '^[a-zA-Z0-9.-]+$' -or $taskUser -notmatch '^[a-zA-Z0-9_-]+$'){throw 'Destino SSH inválido.'}
$taskRoot=Split-Path $PSScriptRoot -Parent
$taskSecret=(Resolve-Path -LiteralPath $SecretsFile).Path
if($taskSecret.StartsWith([IO.Path]::GetFullPath($taskRoot),[StringComparison]::OrdinalIgnoreCase)){throw 'Configuração privada deve estar fora do workspace.'}
$taskHash=(Get-FileHash -LiteralPath $taskSecret -Algorithm SHA256).Hash.ToLowerInvariant()
$taskScript=[IO.File]::ReadAllText((Join-Path $PSScriptRoot 'central-contas-provisionar-privado.sh'),[Text.Encoding]::UTF8)
$taskEncoded=[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($taskScript))
$taskCommand=('bash -c "$(printf ''%s'' ''{0}'' | base64 -d)" -- {1}' -f $taskEncoded,$taskHash)
$taskInfo=[Diagnostics.ProcessStartInfo]::new('ssh.exe');$taskInfo.UseShellExecute=$false;$taskInfo.RedirectStandardInput=$true;$taskInfo.RedirectStandardOutput=$true
foreach($taskArgument in @('-o','ConnectTimeout=15','-o','NumberOfPasswordPrompts=1','-o','PreferredAuthentications=password','-o','PubkeyAuthentication=no',"${taskUser}@${taskServer}",$taskCommand)){[void]$taskInfo.ArgumentList.Add($taskArgument)}
Write-Host 'Provisionamento privado: digite a senha no SSH. Sem deploy, reinício de containers ou ativação do módulo.' -ForegroundColor Cyan
$taskProcess=[Diagnostics.Process]::new();$taskProcess.StartInfo=$taskInfo
try{
    [void]$taskProcess.Start();$taskOutput=$taskProcess.StandardOutput.ReadToEndAsync()
    $taskStream=[IO.File]::OpenRead($taskSecret)
    try{$taskStream.CopyTo($taskProcess.StandardInput.BaseStream);$taskProcess.StandardInput.Close()}finally{$taskStream.Dispose()}
    $taskProcess.WaitForExit();$taskResult=$taskOutput.GetAwaiter().GetResult()
    [IO.File]::WriteAllText((Join-Path $taskRoot '_deploy/mail-server-private-provisioning.txt'),$taskResult,[Text.UTF8Encoding]::new($false))
    if($taskProcess.ExitCode -ne 0){throw 'Provisionamento privado incompleto. Arquivos existentes não foram sobrescritos.'}
    Write-Host $taskResult -ForegroundColor Green
}finally{$taskProcess.Dispose()}
