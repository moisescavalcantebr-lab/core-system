#requires -Version 7.0
param([string]$BaselineRef='e5621d0')
$ErrorActionPreference='Stop'
$taskRoot=Split-Path $PSScriptRoot -Parent
$taskStage=Join-Path $taskRoot ('_deploy/mail-core-overlay-'+[Guid]::NewGuid().ToString('N'))
$taskVendor=Join-Path $taskRoot '_deploy/workspace-update/vendor'
if(!(Test-Path -LiteralPath $taskVendor)){throw 'Prepare primeiro a release local com PackageOnly.'}
$taskShared=@('.htaccess','app/bootstrap/bootstrap.php','app/services/auth/AuthService.php','app/views/layout_admin.php','app/views/partials/header.php','app/views/partials/sidebar.php')
$taskFiles=@($taskShared)+@('app/helpers/mail_center.php','app/helpers/mail_pilot.php','app/helpers/session.php','app/views/partials/mail_folders.php','web/assets/css/admin/central_contas.css','web/assets/js/central_contas.js','web/assets/js/Sortable-1.15.0.min.js','composer.json','composer.lock')
$taskFiles+=@(Get-ChildItem -LiteralPath (Join-Path $taskRoot 'app/console') -File | Where-Object {$_.Name -match '^(check_mail_|inspect_mail_|mail_center_|migrate_mail_center|repair_mail_headers)'} | ForEach-Object {$_.FullName.Substring($taskRoot.Length+1).Replace('\','/')})
$taskFiles+=@(Get-ChildItem -LiteralPath (Join-Path $taskRoot 'app/database') -File -Filter 'mail_center_*.sql' | ForEach-Object {$_.FullName.Substring($taskRoot.Length+1).Replace('\','/')})
$taskDirs=@('app/actions/central_contas','app/services/mail','web/admin/central_contas','web/central-email')
foreach($taskDir in $taskDirs){$taskFiles+=@(Get-ChildItem -LiteralPath (Join-Path $taskRoot $taskDir) -Recurse -File | ForEach-Object {$_.FullName.Substring($taskRoot.Length+1).Replace('\','/')})}
$taskFiles=@($taskFiles | Sort-Object -Unique)
New-Item -ItemType Directory -Path $taskStage | Out-Null
foreach($taskFile in $taskFiles){$taskDestination=Join-Path $taskStage $taskFile; New-Item -ItemType Directory -Force -Path (Split-Path $taskDestination -Parent) | Out-Null; Copy-Item -LiteralPath (Join-Path $taskRoot $taskFile) -Destination $taskDestination}
Copy-Item -LiteralPath $taskVendor -Destination (Join-Path $taskStage 'vendor') -Recurse
Copy-Item -LiteralPath (Join-Path $taskRoot '_deploy/workspace-update/mail-release.json') -Destination $taskStage
$taskEntries=@(Get-ChildItem -LiteralPath $taskStage -Recurse -File | ForEach-Object {@{path=$_.FullName.Substring($taskStage.Length+1).Replace('\','/');sha256=(Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash.ToLowerInvariant()}})
$taskBaselines=@{}
foreach($taskFile in $taskShared){
    $taskInfo=[Diagnostics.ProcessStartInfo]::new('git.exe');$taskInfo.WorkingDirectory=$taskRoot;$taskInfo.UseShellExecute=$false;$taskInfo.RedirectStandardOutput=$true
    [void]$taskInfo.ArgumentList.Add('show');[void]$taskInfo.ArgumentList.Add("${BaselineRef}:$taskFile")
    $taskProcess=[Diagnostics.Process]::Start($taskInfo);$taskBuffer=[IO.MemoryStream]::new()
    try{$taskProcess.StandardOutput.BaseStream.CopyTo($taskBuffer);$taskProcess.WaitForExit();if($taskProcess.ExitCode -ne 0){throw 'Baseline Git não encontrada.'};$taskBytes=[Text.Encoding]::UTF8.GetBytes([Text.Encoding]::UTF8.GetString($taskBuffer.ToArray()).Replace("`r`n","`n"));$taskBaselines[$taskFile]=[Convert]::ToHexString([Security.Cryptography.SHA256]::HashData($taskBytes)).ToLowerInvariant()}finally{$taskProcess.Dispose();$taskBuffer.Dispose()}
}
$taskBaselineCommit=git -C $taskRoot rev-parse $BaselineRef
if($LASTEXITCODE -ne 0){throw 'Referência da baseline inválida.'}
$taskManifest=@{schema=1;mode='mail-center-only-disabled';baseline_commit=$taskBaselineCommit;shared_baselines=$taskBaselines;files=$taskEntries}
[IO.File]::WriteAllText((Join-Path $taskStage 'mail-overlay.json'),($taskManifest | ConvertTo-Json -Depth 6),[Text.UTF8Encoding]::new($false))
$taskArchive=Join-Path $taskRoot '_deploy/mail-core-overlay.tar'
& tar.exe -cf $taskArchive -C $taskStage .
if($LASTEXITCODE -ne 0){throw 'Falha ao empacotar overlay.'}
Write-Output ('mail_overlay_stage='+$taskStage)
Write-Output ('mail_overlay_files='+$taskEntries.Count)
Write-Output ('mail_overlay_sha256='+(Get-FileHash -LiteralPath $taskArchive -Algorithm SHA256).Hash.ToLowerInvariant())
