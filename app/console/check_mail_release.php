<?php
declare(strict_types=1);
// Package-only verification: no bootstrap, environment, database or provider access.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');
try{
    $options=getopt('',['root:','installed']);$root=realpath((string)($options['root']??dirname(__DIR__,2)));if($root===false)throw new RuntimeException();
    if(PHP_VERSION_ID<80300||PHP_VERSION_ID>=90000)throw new RuntimeException();
    $json=static fn(string $file)=>json_decode((string)file_get_contents($root.'/'.$file),true,512,JSON_THROW_ON_ERROR);
    $manifest=$json('mail-release.json');$lock=$json('composer.lock');$composer=$json('composer.json');
    foreach(['composer.lock'=>'lock_sha256','composer.json'=>'composer_sha256']as$file=>$field)if(!hash_equals((string)($manifest[$field]??''),hash_file('sha256',$root.'/'.$file)))throw new RuntimeException();
    foreach(array_keys($composer['require']??[])as$requirement)if(str_starts_with($requirement,'ext-')&&!extension_loaded(substr($requirement,4)))throw new RuntimeException();
    foreach(['pdo_mysql','openssl','iconv']as$extension)if(!extension_loaded($extension))throw new RuntimeException();
    $installed=$json('vendor/composer/installed.json');if(($installed['dev']??true)!==false||!empty($installed['dev-package-names']))throw new RuntimeException();
    $actual=[];foreach($installed['packages']??[]as$package)$actual[$package['name']]=$package;
    if(count($actual)!==count($lock['packages']??[]))throw new RuntimeException();
    foreach($lock['packages']as$package){$found=$actual[$package['name']]??null;if(!$found||$found['version']!==$package['version']||($found['source']['reference']??null)!==($package['source']['reference']??null)||($found['dist']['reference']??null)!==($package['dist']['reference']??null))throw new RuntimeException();}
    foreach(['001_foundation','002_pilot','003_organization','004_reading']as$version)if(!is_file($root.'/app/database/mail_center_'.$version.'.sql'))throw new RuntimeException();
    foreach(['oauth_callback.php','oauth_zoho_callback.php','oauth_outlook_callback.php']as$file)if(!is_file($root.'/web/admin/central_contas/'.$file))throw new RuntimeException();
    $forbidden=['scripts/deploy.local.ps1','secrets.json','auth.json','tls/localhost.key'];if(!isset($options['installed'])){$forbidden[]='env';$forbidden[]='docker/docker-compose.mail-center.prod.yml';}
    foreach($forbidden as$file)if(file_exists($root.'/'.$file))throw new RuntimeException();
    require $root.'/vendor/autoload.php';
    foreach(['League\\OAuth2\\Client\\Provider\\GenericProvider','Symfony\\Component\\Mime\\Email','HTMLPurifier','Egulias\\EmailValidator\\EmailValidator']as$class)if(!class_exists($class))throw new RuntimeException();
    echo 'PASS: release da Central; PHP/extensões, dependências sem dev, lock, migrações 001–004 e callbacks conferidos. Nenhum ambiente ou banco acessado.'.PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'Release da Central inválida: confira runtime, lock, dependências e arquivos obrigatórios. Nenhuma configuração privada foi exibida.'.PHP_EOL);exit(1);}
