#requires -Version 7.0
param([switch]$PackageOnly,[switch]$AvatarFix)
$ErrorActionPreference='Stop'
$taskRoot=Split-Path $PSScriptRoot -Parent
$taskSettings=. (Join-Path $PSScriptRoot 'deploy.local.ps1')
$taskServer=[string]$taskSettings.Server;$taskUser=[string]$taskSettings.User
if($taskServer -ne '157.230.214.72' -or $taskUser -ne 'root'){throw 'Servidor diferente do autorizado.'}
$taskPaths=@(& git -C $taskRoot diff --name-only b50ece0 dc82f9d -- bases/futebol/app bases/futebol/web bases/futebol/modules modules/competicoes modules/meu_time)
if($LASTEXITCODE -ne 0 -or $taskPaths.Count -ne 28){throw 'Lista de arquivos inesperada.'}
$taskRevision='dc82f9d';$taskBeforeRevision='b50ece0'
if($AvatarFix){
 $taskPaths=@('.htaccess')
 foreach($taskPrefix in @('modules/meu_time','bases/futebol/modules/meu_time','bases/futebol')){foreach($taskName in @('index.php','appearance.php')){$taskPaths+="$taskPrefix/web/admin/meu_time/$taskName"}}
 $taskRevision='HEAD';$taskBeforeRevision='eddbf3e'
}
$taskStage=Join-Path $taskRoot ('_deploy/futebol-release-'+[Guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $taskStage | Out-Null
function Get-TaskHash([string]$Text){[Convert]::ToHexString([Security.Cryptography.SHA256]::HashData([Text.Encoding]::UTF8.GetBytes($Text.Replace("`r`n","`n")))).ToLowerInvariant()}
$taskEntries=@()
foreach($taskPath in $taskPaths){
 $taskText=[IO.File]::ReadAllText((Join-Path $taskRoot $taskPath)).Replace("`r`n","`n")
 $taskHash=Get-TaskHash $taskText
 $taskCommitted=((& git -C $taskRoot show "${taskRevision}:$taskPath") -join "`n")+"`n"
 if($LASTEXITCODE -ne 0 -or (Get-TaskHash $taskCommitted) -ne $taskHash){throw "Arquivo fora da versão revisada: $taskPath"}
 $taskHasBefore=(& git -C $taskRoot ls-tree $taskBeforeRevision -- $taskPath)
 $taskAllowed=@($taskHash)
 if($taskHasBefore){$taskBefore=((& git -C $taskRoot show "${taskBeforeRevision}:$taskPath") -join "`n")+"`n";if($LASTEXITCODE -ne 0){throw 'Baseline inválido'};$taskAllowed+=Get-TaskHash $taskBefore}
 $taskDestination=Join-Path $taskStage $taskPath
 New-Item -ItemType Directory -Force -Path (Split-Path $taskDestination -Parent) | Out-Null
 [IO.File]::WriteAllText($taskDestination,$taskText,[Text.UTF8Encoding]::new($false))
 $taskEntries+=@{path=$taskPath;sha256=$taskHash;allowed=@($taskAllowed|Sort-Object -Unique);allow_missing=!$taskHasBefore}
}
[IO.File]::WriteAllText((Join-Path $taskStage 'manifest.json'),(@{files=$taskEntries;mode=$(if($AvatarFix){'avatars'}else{'full'})}|ConvertTo-Json -Depth 6),[Text.UTF8Encoding]::new($false))
Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'futebol-conferir-publicacao.php') -Destination (Join-Path $taskStage 'check.php')
$taskArchive=$taskStage+'.tar'
& tar.exe -cf $taskArchive -C $taskStage .
if($LASTEXITCODE -ne 0){throw 'Empacotamento falhou.'}
$taskHash=(Get-FileHash -LiteralPath $taskArchive -Algorithm SHA256).Hash.ToLowerInvariant()
Write-Host "Pacote Futebol: $($taskPaths.Count) arquivos revisados e cópias correspondentes nos projetos instalados."
if($PackageOnly){Write-Output "package_sha256=$taskHash";exit 0}
$taskScript=[IO.File]::ReadAllText((Join-Path $PSScriptRoot 'futebol-publicar.sh')).Replace("`r`n","`n")
$taskEncoded=[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($taskScript))
$taskCommand=('bash -c "$(printf ''%s'' ''{0}'' | base64 -d)" -- {1}' -f $taskEncoded,$taskHash)

$taskInfo=[Diagnostics.ProcessStartInfo]::new('ssh.exe');$taskInfo.UseShellExecute=$false;$taskInfo.RedirectStandardInput=$true;$taskInfo.RedirectStandardOutput=$true;$taskInfo.RedirectStandardError=$true
foreach($taskArg in @('-o','ConnectTimeout=15','-o','ServerAliveInterval=15','-o','ServerAliveCountMax=4','-o','NumberOfPasswordPrompts=1','-o','PreferredAuthentications=password','-o','PubkeyAuthentication=no',"${taskUser}@${taskServer}",$taskCommand)){[void]$taskInfo.ArgumentList.Add($taskArg)}
Write-Host 'Digite a senha SSH somente neste terminal. Publicação de Futebol com backup e reversão. Configurações, bancos e e-mails não serão alterados.' -ForegroundColor Cyan
$taskProcess=[Diagnostics.Process]::new();$taskProcess.StartInfo=$taskInfo
try{
 [void]$taskProcess.Start();$taskErrors=$taskProcess.StandardError.ReadToEndAsync();$taskStream=[IO.File]::OpenRead($taskArchive)
 try{$taskStream.CopyTo($taskProcess.StandardInput.BaseStream);$taskProcess.StandardInput.Close()}finally{$taskStream.Dispose()}
 $taskReport=Join-Path $taskRoot '_deploy/futebol-publication.txt';[IO.File]::WriteAllText($taskReport,'',[Text.UTF8Encoding]::new($false))
 while($null -ne ($taskLine=$taskProcess.StandardOutput.ReadLine())){[IO.File]::AppendAllText($taskReport,$taskLine+"`n",[Text.UTF8Encoding]::new($false));Write-Host $taskLine}
 $taskProcess.WaitForExit()
 $taskErrorText=$taskErrors.GetAwaiter().GetResult();if($taskErrorText){[IO.File]::AppendAllText($taskReport,$taskErrorText,[Text.UTF8Encoding]::new($false));Write-Host $taskErrorText}
 if($taskProcess.ExitCode -ne 0){throw 'Publicação não concluída. Confira o relatório antes de repetir.'}
}finally{$taskProcess.Dispose()}
