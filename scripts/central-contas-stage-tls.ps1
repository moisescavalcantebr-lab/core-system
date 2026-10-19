#requires -Version 7.0
param([Parameter(Mandatory=$true)][string]$PrivateDirectory,[string]$Config='')
$ErrorActionPreference='Stop'
if(!$Config){$Config=Join-Path $PSScriptRoot 'deploy.local.ps1'}
$taskSettings=. $Config
$taskServer=[string]$taskSettings.Server;$taskUser=[string]$taskSettings.User
if($taskServer -ne '157.230.214.72' -or $taskUser -notmatch '^[a-zA-Z0-9_-]+$'){throw 'Destino SSH inválido.'}
$taskRoot=Split-Path $PSScriptRoot -Parent
$taskPrivate=(Resolve-Path -LiteralPath $PrivateDirectory).Path
if($taskPrivate.StartsWith($taskRoot,[StringComparison]::OrdinalIgnoreCase)){throw 'Pasta privada deve ser externa ao workspace.'}
$taskTls=Join-Path $taskPrivate 'production/tls'
$taskBundle=Join-Path $taskTls ('stage-'+[Guid]::NewGuid().ToString('N')+'.tar')
$taskCa=Join-Path $taskRoot '_deploy'
$taskApache=Join-Path $taskRoot 'docker/php'
try{
    & tar.exe -cf $taskBundle -C $taskPrivate origin-cert.pem -C $taskTls privkey.pem -C $taskCa cloudflare-origin-ca-rsa.pem -C $taskApache mail-center-origin-ssl.example.conf mail-center.conf
    if($LASTEXITCODE -ne 0){throw 'Não foi possível preparar o arquivo TLS privado.'}
    $taskHash=(Get-FileHash -LiteralPath $taskBundle -Algorithm SHA256).Hash.ToLowerInvariant()
    $taskScript=[IO.File]::ReadAllText((Join-Path $PSScriptRoot 'central-contas-stage-tls.sh')).Replace("`r`n","`n")
    $taskEncoded=[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($taskScript))
    $taskCommand=('bash -c "$(printf ''%s'' ''{0}'' | base64 -d)" -- {1}' -f $taskEncoded,$taskHash)
    $taskInfo=[Diagnostics.ProcessStartInfo]::new('ssh.exe');$taskInfo.UseShellExecute=$false;$taskInfo.RedirectStandardInput=$true;$taskInfo.RedirectStandardOutput=$true
    foreach($taskArg in @('-o','ConnectTimeout=15','-o','NumberOfPasswordPrompts=1','-o','PreferredAuthentications=password','-o','PubkeyAuthentication=no',"${taskUser}@${taskServer}",$taskCommand)){[void]$taskInfo.ArgumentList.Add($taskArg)}
    Write-Host 'Preparação TLS inativa: digite a senha SSH. Sem reinício ou alteração do Core.' -ForegroundColor Cyan
    $taskProcess=[Diagnostics.Process]::new();$taskProcess.StartInfo=$taskInfo
    try{
        [void]$taskProcess.Start();$taskOutput=$taskProcess.StandardOutput.ReadToEndAsync()
        $taskStream=[IO.File]::OpenRead($taskBundle)
        try{$taskStream.CopyTo($taskProcess.StandardInput.BaseStream);$taskProcess.StandardInput.Close()}finally{$taskStream.Dispose()}
        $taskProcess.WaitForExit();$taskResult=$taskOutput.GetAwaiter().GetResult()
        [IO.File]::WriteAllText((Join-Path $taskRoot '_deploy/mail-server-tls-staging.txt'),$taskResult,[Text.UTF8Encoding]::new($false))
        if($taskProcess.ExitCode -ne 0){throw 'Preparação TLS não concluída; configuração ativa preservada.'}
        Write-Host $taskResult -ForegroundColor Green
    }finally{$taskProcess.Dispose()}
}finally{
    if(Test-Path -LiteralPath $taskBundle){Remove-Item -LiteralPath $taskBundle -Force}
}
