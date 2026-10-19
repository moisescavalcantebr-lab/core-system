param(
    [Parameter(Mandatory=$true)][string]$BackupDirectory,
    [Parameter(Mandatory=$true)][string]$PrivateLocalDirectory,
    [string]$Config=''
)
$ErrorActionPreference='Stop'
if(!$Config){$Config=Join-Path $PSScriptRoot 'deploy.local.ps1'}
if($BackupDirectory -notmatch '^/opt/backups/central-email/before-mail-[a-zA-Z0-9-]+$'){throw 'Backup remoto inválido.'}
$taskSettings=. $Config;$taskServer=[string]$taskSettings.Server;$taskUser=[string]$taskSettings.User
if($taskServer -notmatch '^[a-zA-Z0-9.-]+$' -or $taskUser -notmatch '^[a-zA-Z0-9_-]+$'){throw 'Destino SSH inválido.'}
$taskWorkspace=Split-Path $PSScriptRoot -Parent
$taskPrivate=[IO.Path]::GetFullPath($PrivateLocalDirectory).TrimEnd('\','/')
if($taskPrivate.StartsWith([IO.Path]::GetFullPath($taskWorkspace),[StringComparison]::OrdinalIgnoreCase)){throw 'Restauração temporária deve ficar fora do workspace.'}
if(!(Test-Path -LiteralPath $taskPrivate -PathType Container)){throw 'Pasta privada restrita inexistente.'}
$taskDirectory=Join-Path $taskPrivate ('restore-'+($BackupDirectory.Split('/')[-1]))
New-Item -ItemType Directory -Path $taskDirectory -Force | Out-Null
$taskDump=Join-Path $taskDirectory 'core.sql.gz'
$taskComplete=Join-Path $taskDirectory 'download.complete'
$taskDockerCommand=Get-Command docker -ErrorAction SilentlyContinue
$taskDocker=if($taskDockerCommand){$taskDockerCommand.Source}else{'C:\Program Files\Docker\Docker\resources\bin\docker.exe'}
$taskContainer='mail-restore-'+[guid]::NewGuid().ToString('N')
$taskCreated=$false;$taskSuccess=$false
function Invoke-RestoreDocker([string[]]$NativeArguments){
    $taskOldPreference=$ErrorActionPreference;$ErrorActionPreference='Continue'
    try{$taskNativeOutput=& $taskDocker @NativeArguments 2>&1;$taskExitCode=$LASTEXITCODE}finally{$ErrorActionPreference=$taskOldPreference}
    if($taskExitCode -ne 0){throw 'Falha na validação Docker; detalhes privados omitidos.'}
    return ($taskNativeOutput -join "`n")
}
try{
    if(!(Test-Path -LiteralPath $taskComplete) -or !(Test-Path -LiteralPath $taskDump)){
    Write-Host 'Validação isolada: digite a senha diretamente no SSH. O banco do servidor não será alterado.' -ForegroundColor Cyan
    $taskSshInfo=New-Object Diagnostics.ProcessStartInfo
    $taskSshInfo.FileName='ssh.exe';$taskSshInfo.UseShellExecute=$false;$taskSshInfo.RedirectStandardOutput=$true
    $taskSshInfo.Arguments="-o ConnectTimeout=15 -o NumberOfPasswordPrompts=1 -o PreferredAuthentications=password -o PubkeyAuthentication=no ${taskUser}@${taskServer} `"cat '$BackupDirectory/core.sql.gz'`""
    $taskSsh=New-Object Diagnostics.Process;$taskSsh.StartInfo=$taskSshInfo
    $taskFile=[IO.File]::Create($taskDump)
    try{[void]$taskSsh.Start();$taskSsh.StandardOutput.BaseStream.CopyTo($taskFile);$taskSsh.WaitForExit();if($taskSsh.ExitCode -ne 0){throw 'Download protegido do backup falhou.'}}finally{$taskFile.Dispose();$taskSsh.Dispose()}
    [IO.File]::WriteAllText($taskComplete,'complete')
    }else{Write-Host 'Reutilizando cópia temporária protegida do mesmo backup.' -ForegroundColor Cyan}
    $null=Invoke-RestoreDocker @('run','-d','--rm','--network','none','--memory','768m','--cpus','2','--name',$taskContainer,'--hostname','mail-center-restore-test','-e','MYSQL_ALLOW_EMPTY_PASSWORD=yes','mysql:8.0','--skip-log-bin','--performance-schema=OFF','--innodb-buffer-pool-size=64M','--max-connections=20')
    $taskCreated=$true;$taskReady=$false
    $taskOldPreference=$ErrorActionPreference;$ErrorActionPreference='Continue'
    try{for($taskAttempt=0;$taskAttempt -lt 60;$taskAttempt++){
        & $taskDocker exec $taskContainer mysqladmin '--host=127.0.0.1' '--user=root' ping --silent 2>&1 | Out-Null
        if($LASTEXITCODE -eq 0){$taskReady=$true;break};Start-Sleep -Seconds 1
    }}finally{$ErrorActionPreference=$taskOldPreference}
    if(!$taskReady){throw 'Banco isolado não ficou disponível.'}
    $taskImportInfo=New-Object Diagnostics.ProcessStartInfo
    $taskImportInfo.FileName=$taskDocker;$taskImportInfo.Arguments="exec -i $taskContainer mysql -uroot";$taskImportInfo.UseShellExecute=$false;$taskImportInfo.RedirectStandardInput=$true;$taskImportInfo.RedirectStandardOutput=$true;$taskImportInfo.RedirectStandardError=$true
    $taskImport=New-Object Diagnostics.Process;$taskImport.StartInfo=$taskImportInfo
    [void]$taskImport.Start();$taskStdout=$taskImport.StandardOutput.ReadToEndAsync();$taskStderr=$taskImport.StandardError.ReadToEndAsync()
    $taskSource=[IO.File]::OpenRead($taskDump);$taskGzip=New-Object IO.Compression.GZipStream($taskSource,[IO.Compression.CompressionMode]::Decompress)
    try{$taskGzip.CopyTo($taskImport.StandardInput.BaseStream);$taskImport.StandardInput.Close();$taskImport.WaitForExit();if($taskImport.ExitCode -ne 0){throw 'Restauração falhou; detalhes privados omitidos.'}}finally{$taskGzip.Dispose();$taskSource.Dispose();$taskImport.Dispose()}
    $taskResult=Invoke-RestoreDocker @('run','--rm','--network',"container:$taskContainer",'--mount',"type=bind,source=$taskWorkspace,target=/var/www/html,readonly",'-e','MAIL_CENTER_REHEARSAL=isolated','core-mail-runtime-check:20261019','php','/var/www/html/scripts/tests/mail_center_restoration_test.php')
    Write-Host $taskResult -ForegroundColor Green
    [IO.File]::WriteAllText((Join-Path $taskWorkspace '_deploy/mail-server-restoration.txt'),$taskResult,(New-Object Text.UTF8Encoding($false)))
    $taskSuccess=$true
}finally{
    if($taskCreated){& $taskDocker rm -fv $taskContainer 2>&1 | Out-Null}
    if($taskSuccess){
        foreach($taskFile in @($taskDump,$taskComplete)){if(Test-Path -LiteralPath $taskFile){Remove-Item -LiteralPath $taskFile -Force}}
        if(Test-Path -LiteralPath $taskDirectory){Remove-Item -LiteralPath $taskDirectory -Force}
    }else{Write-Host 'Ensaio incompleto. Cópia temporária mantida na pasta privada restrita para repetir sem novo download.' -ForegroundColor Yellow}
}
