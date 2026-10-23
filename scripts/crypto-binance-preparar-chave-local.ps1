param([string]$PrivateRoot = (Join-Path (Split-Path $PSScriptRoot -Parent) '..\private-crypto-binance'))
$ErrorActionPreference='Stop'
$docker='C:\Program Files\Docker\Docker\resources\bin\docker.exe'
function Docker([string[]]$Arguments) {
    $result=& $docker @Arguments
    if($LASTEXITCODE -ne 0){throw "Docker falhou: $($Arguments[0])"}
    return $result
}
if((Docker @('context','show')) -ne 'desktop-linux'){throw 'Somente Docker local.'}
$workspace=Split-Path $PSScriptRoot -Parent
$app=@(Docker @('inspect','app_php') | ConvertFrom-Json)[0]
$db=@(Docker @('inspect','app_db') | ConvertFrom-Json)[0]
if($app.Config.Labels.'com.docker.compose.project' -ne 'docker' -or [IO.Path]::GetFullPath(($app.Mounts | Where-Object Destination -eq '/var/www/html').Source) -ne [IO.Path]::GetFullPath($workspace)){throw 'Stack local inesperada.'}
Docker @('exec','app_php','php','/usr/local/lib/core-verify-runtime.php')
$private=[IO.Path]::GetFullPath($PrivateRoot)
if($private.StartsWith([IO.Path]::GetFullPath($workspace)+'\',[StringComparison]::OrdinalIgnoreCase)){throw 'Chave deve ficar fora do site.'}
New-Item -ItemType Directory -Path $private -Force | Out-Null
$identity=[Security.Principal.WindowsIdentity]::GetCurrent().Name
& icacls.exe $private /inheritance:r /grant:r "${identity}:(OI)(CI)F" | Out-Null
if($LASTEXITCODE -ne 0){throw 'Falha ao proteger pasta privada.'}
$backupKey=Join-Path $private 'master.key'
$volume='docker_crypto_binance_keys'
& $docker volume inspect $volume > $null 2>&1
if($LASTEXITCODE -ne 0){Docker @('volume','create',$volume) | Out-Null}
$helper='crypto-key-init-'+[Guid]::NewGuid().ToString('N').Substring(0,10)
try {
    Docker @('create','--name',$helper,'--network','none','--mount',"type=volume,src=$volume,dst=/keys",'--entrypoint','sleep',$app.Image,'300') | Out-Null
    Docker @('start',$helper) | Out-Null
    & $docker exec $helper test -f /keys/master.key
    $hasKey=$LASTEXITCODE -eq 0
    if(!$hasKey -and (Test-Path -LiteralPath $backupKey)){
        Docker @('cp',$backupKey,"${helper}:/keys/master.key") | Out-Null
    } elseif(!$hasKey) {
        $check='$c=require "env/env.production.php";$d=$c["db"];$p=new PDO("mysql:host=".$d["host"].";charset=utf8mb4",$d["user"],$d["pass"]);$total=0;foreach($p->query("SELECT TABLE_SCHEMA FROM information_schema.TABLES WHERE TABLE_NAME=\"crypto_binance_connections\"")->fetchAll(PDO::FETCH_COLUMN) as $name){$total+=(int)$p->query("SELECT COUNT(*) FROM `".str_replace("`","``",$name)."`.crypto_binance_connections")->fetchColumn();}echo $total;'
        $saved=([string](Docker @('exec','app_php','php','-r',$check))).Trim()
        if($saved -notmatch '^\d+$' -or [int]$saved -gt 0){throw 'Ha conexoes salvas ou estado desconhecido. Restaure a chave original; nao gerar outra.'}
    }
    Docker @('cp',(Join-Path $PSScriptRoot 'crypto-binance-key-init.php'),"${helper}:/tmp/key-init.php") | Out-Null
    Docker @('exec',$helper,'php','/tmp/key-init.php')
    $remoteHash=([string](Docker @('exec',$helper,'sha256sum','/keys/master.key'))).Split(' ')[0]
    if(Test-Path -LiteralPath $backupKey){
        if((Get-FileHash -LiteralPath $backupKey -Algorithm SHA256).Hash.ToLower() -ne $remoteHash){throw 'Backup de chave difere do volume. Nenhum arquivo foi sobrescrito.'}
    } else {Docker @('cp',"${helper}:/keys/master.key",$backupKey) | Out-Null}
} finally {
    & $docker container inspect $helper > $null 2>&1
    if($LASTEXITCODE -eq 0){Docker @('rm','-f',$helper) | Out-Null}
}
$files=$app.Config.Labels.'com.docker.compose.project.config_files'.Split(',')
$override=Join-Path $workspace 'docker/docker-compose.crypto.local.yml'
$mounted=@($app.Mounts | Where-Object Destination -eq '/run/crypto-binance')
if($mounted.Count -gt 0){
    if($mounted[0].Type -ne 'volume' -or $mounted[0].Name -ne $volume -or $mounted[0].RW){throw 'Mount da chave inesperado.'}
} else {
    $privateBefore=$env:MAIL_CENTER_PRIVATE_DIR
    $env:MAIL_CENTER_PRIVATE_DIR=Split-Path (($app.Mounts | Where-Object Destination -eq '/run/mail-center/secrets.json').Source) -Parent
    $compose=@('compose','-p','docker')
    foreach($file in $files){$compose+=@('-f',$file)}
    $snapshot=Join-Path $private ('before-mount-'+(Get-Date -Format yyyyMMddHHmmss)+'.json')
    Docker @('inspect','app_php','app_db') | Set-Content -LiteralPath $snapshot -Encoding utf8
    try {
        Docker ($compose+@('-f',$override,'config','--quiet'))
        try {
            Docker ($compose+@('-f',$override,'up','-d','--no-build','--no-deps','--force-recreate','app'))
            $after=@(Docker @('inspect','app_php') | ConvertFrom-Json)[0]
            foreach($mount in $app.Mounts){
                $match=@($after.Mounts | Where-Object Destination -eq $mount.Destination)
                if($match.Count -ne 1 -or [IO.Path]::GetFullPath($match[0].Source) -ne [IO.Path]::GetFullPath($mount.Source) -or $match[0].RW -ne $mount.RW){throw 'Mount anterior mudou.'}
            }
            if(($app.HostConfig.PortBindings | ConvertTo-Json -Depth 5 -Compress) -ne ($after.HostConfig.PortBindings | ConvertTo-Json -Depth 5 -Compress)){throw 'Portas mudaram.'}
            if((Docker @('inspect','app_db','--format','{{.Id}}')) -ne $db.Id){throw 'Container MySQL mudou.'}
            if($after.Image -ne $app.Image){throw 'Imagem PHP mudou.'}
            Docker @('exec','app_php','php','/usr/local/lib/core-verify-runtime.php')
        } catch {
            Docker ($compose+@('up','-d','--no-build','--no-deps','--force-recreate','app')) | Out-Null
            throw
        }
    } finally {$env:MAIL_CENTER_PRIVATE_DIR=$privateBefore}
}
Docker @('exec','--user','www-data','app_php','php','-r','if(!is_readable("/run/crypto-binance/master.key"))exit(1);echo "PASS: private key readable by application, contents hidden.\n";')
Write-Output "PASS: chave em volume separado e somente leitura; backup protegido em $private."
