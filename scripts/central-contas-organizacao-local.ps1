#requires -Version 7.0
param([ValidateSet('Enable','Disable')][string]$Mode='Enable',[string]$PrivateDirectory='')
$ErrorActionPreference='Stop'
$taskRoot=Split-Path $PSScriptRoot -Parent
$taskEnv=Join-Path $taskRoot 'env/env.production.php'
if(!$PrivateDirectory){$PrivateDirectory=Join-Path (Split-Path $taskRoot -Parent) 'private-mail-center'}
$taskPrivate=[IO.Path]::GetFullPath($PrivateDirectory)
if(!(Test-Path -LiteralPath $taskPrivate -PathType Container)-or $taskPrivate.Equals($taskRoot,[StringComparison]::OrdinalIgnoreCase)-or $taskPrivate.StartsWith($taskRoot+[IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase)){throw 'Use a pasta privada existente fora do workspace.'}
& git -C $taskRoot check-ignore -q -- env/env.production.php
if($LASTEXITCODE -ne 0){throw 'O ENV local precisa estar fora do versionamento.'}
$taskDocker='C:\Program Files\Docker\Docker\resources\bin\docker.exe'
$taskProbe='$c=require "/var/www/html/env/env.production.php";$url=$c["app_url"]??"";echo json_encode(["local"=>parse_url($url,PHP_URL_SCHEME)==="https"&&in_array(parse_url($url,PHP_URL_HOST),["localhost","127.0.0.1"],true),"hash"=>hash_file("sha256","/var/www/html/env/env.production.php")]);'
$taskRaw=& $taskDocker exec app_php php -r $taskProbe
if($LASTEXITCODE -ne 0){throw 'Não foi possível validar o Docker local.'}
$taskState=$taskRaw|ConvertFrom-Json
if($taskState.local -ne $true -or $taskState.hash -ne (Get-FileHash -LiteralPath $taskEnv -Algorithm SHA256).Hash.ToLowerInvariant()){throw 'O container não corresponde ao ENV HTTPS local deste workspace.'}
$taskSource=[IO.File]::ReadAllText($taskEnv)
$taskValue=if($Mode -eq 'Enable'){'true'}else{'false'}
$taskOption=[regex]::new('([''"])remote_organization_enabled\1\s*=>\s*(true|false)')
if($taskOption.Matches($taskSource).Count -gt 1){throw 'Opção duplicada no ENV.'}
if($taskOption.IsMatch($taskSource)){
    $taskMatch=$taskOption.Match($taskSource);$taskValueGroup=$taskMatch.Groups[2]
    $taskUpdated=$taskSource.Remove($taskValueGroup.Index,$taskValueGroup.Length).Insert($taskValueGroup.Index,$taskValue)
}else{
    $taskSection=[regex]::new('([''"])mail_center\1\s*=>\s*(?:array\s*\(|\[)')
    $taskMatches=$taskSection.Matches($taskSource)
    if($taskMatches.Count -ne 1){throw 'Estrutura do ENV diferente da revisada. Nenhum arquivo alterado.'}
    $taskUpdated=$taskSource.Insert($taskMatches[0].Index+$taskMatches[0].Length,"`n        'remote_organization_enabled' => $taskValue,")
}
if($taskUpdated -eq $taskSource){Write-Host "local_organization_mode=$Mode (já configurado)";exit 0}
$taskBackupDirectory=Join-Path $taskPrivate 'local-config-backups'
[IO.Directory]::CreateDirectory($taskBackupDirectory)>$null
$taskBackup=Join-Path $taskBackupDirectory ('env-before-organization-'+[DateTime]::UtcNow.ToString('yyyyMMddTHHmmssfffZ')+'.php')
Copy-Item -LiteralPath $taskEnv -Destination $taskBackup -ErrorAction Stop
try{
    [IO.File]::WriteAllText($taskEnv,$taskUpdated,[Text.UTF8Encoding]::new($false))
    & $taskDocker exec app_php php -l /var/www/html/env/env.production.php
    if($LASTEXITCODE -ne 0){throw 'Sintaxe inválida.'}
    $taskCheck='$c=require "/var/www/html/env/env.production.php";echo ($c["mail_center"]["remote_organization_enabled"]??null)===__VALUE__?"local_organization_check=PASS\n":"local_organization_check=FAIL\n";if(($c["mail_center"]["remote_organization_enabled"]??null)!==__VALUE__)exit(1);'
    & $taskDocker exec app_php php -r $taskCheck.Replace('__VALUE__',$taskValue)
    if($LASTEXITCODE -ne 0){throw 'Ativação local não confirmada.'}
}catch{
    Copy-Item -LiteralPath $taskBackup -Destination $taskEnv -Force
    throw
}
Write-Host "local_organization_mode=$Mode"
Write-Host 'production_changed=no'
Write-Host 'provider_calls=no'
Write-Host 'oauth_consent_granted=no'
