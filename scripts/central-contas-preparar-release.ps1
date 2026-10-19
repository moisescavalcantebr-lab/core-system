param(
    [Parameter(Mandatory=$true)][string]$StagePath,
    [string]$ContainerName='app_php',
    [string]$ComposerPharPath=''
)
$ErrorActionPreference='Stop'
$taskWorkspace=[IO.Path]::GetFullPath((Split-Path $PSScriptRoot -Parent)).TrimEnd('\','/')
$taskStage=[IO.Path]::GetFullPath($StagePath).TrimEnd('\','/')
$taskAllowed=[IO.Path]::GetFullPath((Join-Path $taskWorkspace '_deploy')).TrimEnd('\','/')+[IO.Path]::DirectorySeparatorChar
if(!$taskStage.StartsWith($taskAllowed,[StringComparison]::OrdinalIgnoreCase)){throw 'Staging deve ficar em uma subpasta de _deploy.'}
if(!(Test-Path -LiteralPath $taskStage -PathType Container)){throw 'Staging inexistente.'}
if(!$ComposerPharPath){$ComposerPharPath=Join-Path $taskWorkspace '_deploy/tools/composer.phar'}
$taskComposer=[IO.Path]::GetFullPath($ComposerPharPath)
if(!$taskComposer.StartsWith($taskWorkspace+[IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase) -or !(Test-Path -LiteralPath $taskComposer -PathType Leaf)){throw 'Prepare o Composer verificado em _deploy/tools/composer.phar antes de montar o pacote.'}
if(Test-Path -LiteralPath (Join-Path $taskStage 'vendor')){throw 'Use staging novo, sem vendor existente.'}
foreach($taskFile in @('composer.json','composer.lock')){Copy-Item -LiteralPath (Join-Path $taskWorkspace $taskFile) -Destination (Join-Path $taskStage $taskFile)}
$taskDockerCommand=Get-Command docker -ErrorAction SilentlyContinue
$taskDocker=if($taskDockerCommand){$taskDockerCommand.Source}else{'C:\Program Files\Docker\Docker\resources\bin\docker.exe'}
$taskContainerStage='/var/www/html/'+$taskStage.Substring($taskWorkspace.Length+1).Replace('\','/')
$taskContainerComposer='/var/www/html/'+$taskComposer.Substring($taskWorkspace.Length+1).Replace('\','/')
$taskDockerPrefix=@('exec','-e','COMPOSER_HOME=/tmp/mail-release-composer-home','-e','COMPOSER_CACHE_DIR=/tmp/mail-release-composer-cache','-e','COMPOSER_ALLOW_SUPERUSER=1',$ContainerName,'php',$taskContainerComposer)
& $taskDocker @taskDockerPrefix validate --strict --no-check-publish --no-interaction "--working-dir=$taskContainerStage"
if($LASTEXITCODE -ne 0){throw 'composer.json/lock divergentes; pacote interrompido.'}
& $taskDocker @taskDockerPrefix install --no-dev --no-scripts --no-plugins --prefer-dist --optimize-autoloader --no-interaction "--working-dir=$taskContainerStage"
if($LASTEXITCODE -ne 0){throw 'Instalação congelada das dependências falhou.'}
& $taskDocker @taskDockerPrefix check-platform-reqs --no-dev "--working-dir=$taskContainerStage"
if($LASTEXITCODE -ne 0){throw 'Runtime local incompatível com as dependências.'}
$taskManifest=@{schema=1;lock_sha256=(Get-FileHash -LiteralPath (Join-Path $taskStage 'composer.lock') -Algorithm SHA256).Hash.ToLowerInvariant();composer_sha256=(Get-FileHash -LiteralPath (Join-Path $taskStage 'composer.json') -Algorithm SHA256).Hash.ToLowerInvariant();dependency_mode='production-no-dev'} | ConvertTo-Json
[IO.File]::WriteAllText((Join-Path $taskStage 'mail-release.json'),$taskManifest,(New-Object Text.UTF8Encoding($false)))
& $taskDocker exec $ContainerName php "$taskContainerStage/app/console/check_mail_release.php" "--root=$taskContainerStage"
if($LASTEXITCODE -ne 0){throw 'Verificação final da release falhou.'}
Write-Host '[mail] Dependências de produção e migrações prontas no pacote local.' -ForegroundColor Green
