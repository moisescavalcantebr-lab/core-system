#!/usr/bin/env bash
set -euo pipefail
umask 077
[[ "${1:-}" == --enable-reviewed-owner ]] || exit 1
private=/opt/private-mail-center
config="$private/core-config.php"
[[ -f "$config" && ! -L "$config" ]] || exit 1
exec 9>"$private/mail-release.lock"
flock -n 9 || exit 1
backup=$(mktemp -d "$private/owner-enable-$(date -u +%Y%m%dT%H%M%SZ)-XXXXXXXX")
cp -a "$config" "$backup/core-config-before.php"
before_secret=$(sha256sum "$private/secrets.json");before_secret=${before_secret%% *}
# No bootstrap, provider calls, tokens, message bodies or credential output.
docker exec -i --user www-data app_php php <<'PHP'
<?php
declare(strict_types=1);
ini_set('display_errors','0');
try {
    define('ROOT_PATH','/var/www/html');define('APP_PATH',ROOT_PATH.'/app');
    $c=require ROOT_PATH.'/env/env.production.php';$db=$c['db'];
    require APP_PATH.'/helpers/mail_center.php';require APP_PATH.'/helpers/mail_pilot.php';
    mailPilotLoadCode();require APP_PATH.'/services/mail/MailAccountService.php';
    $pdo=new PDO('mysql:host='.$db['host'].';dbname='.$db['name'].';charset=utf8mb4',$db['user'],$db['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $s=$pdo->prepare("SELECT id FROM core_users WHERE LOWER(email)=LOWER(?) AND status=1 AND role IN ('ADMIN','SUPER_ADMIN')");$s->execute(['moises.cavalcante@meuprojetoweb.com']);$owners=$s->fetchAll(PDO::FETCH_COLUMN);
    if(count($owners)!==1||(int)$owners[0]!==($c['mail_center']['owner_user_id']??0))throw new RuntimeException();
    $owner=(int)$owners[0];$private=MailPilotConfig::load($c,ROOT_PATH);
    $providers=[];foreach(['gmail','zoho']as$p)$providers[$p]=MailPilotConfig::forProvider($private,$c,$p);
    if(($providers['gmail']['pilot_email']??'')!=='moises.cavalcante.br@gmail.com'||($providers['zoho']['pilot_email']??'')!=='moises.cavalcante@meuprojetoweb.com')throw new RuntimeException();
    if(!mailCenterOrganizationReady($pdo)||!is_dir($private['attachments_dir'])||!is_writable($private['attachments_dir']))throw new RuntimeException();
    $service=new MailAccountService($pdo,$owner);$ids=array_column($service->providers(),'id','code');
    $categories=array_column($service->categories(),'id','name');$created=0;
    foreach([
        ['provider'=>'gmail','email'=>$providers['gmail']['pilot_email'],'label'=>'Gmail','function'=>'Cadastros e plataformas','color'=>'#3b82f6','purpose'=>'Cadastros em plataformas e sites.','order'=>0],
        ['provider'=>'zoho','email'=>$providers['zoho']['pilot_email'],'label'=>'Zoho Mail','function'=>'Projeto','color'=>'#14b8a6','purpose'=>'Centralizar os e-mails do projeto.','order'=>1],
    ]as$box){
        $existing=false;foreach($service->accounts()as$a)if($a['email']===$box['email']){$existing=true;break;}
        if($existing)continue;
        $category=$categories[$box['function']]??null;
        if(!$category){$category=$service->saveCategory(['name'=>$box['function'],'color'=>$box['color'],'display_order'=>$box['order']]);$categories[$box['function']]=$category;}
        $service->save(['provider_id'=>(int)$ids[$box['provider']],'email'=>$box['email'],'label'=>$box['label'],'category_id'=>(int)$category,'purpose'=>$box['purpose'],'display_order'=>$box['order']]);$created++;
    }
    echo 'owner_identity_verified=yes'.PHP_EOL;
    echo 'gmail_zoho_private_config_valid=yes'.PHP_EOL;
    echo 'account_metadata_created='.$created.PHP_EOL;
    echo 'real_provider_calls=no'.PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,"Habilitação interrompida antes de liberar acesso; detalhes privados omitidos.\n");exit(1);}
PHP
new_config=$(docker exec app_php php -r '$c=require "/run/mail-center/core-config.php";$c["enabled"]=true;$c["oauth_log_redaction_verified"]=true;echo base64_encode("<?php\nreturn ".var_export($c,true).";\n");')
printf '%s' "$new_config" | base64 -d >"$backup/core-config-new.php"
before_config=$(sha256sum "$config");before_config=${before_config%% *}
expected_config=$(sha256sum "$backup/core-config-before.php");expected_config=${expected_config%% *}
[[ "$before_config" == "$expected_config" ]] || exit 1
armed=no
completed=no
finish(){
    result=$?
    trap - EXIT INT TERM
    if [[ "$armed" == yes && "$completed" != yes ]]; then
        cat "$backup/core-config-before.php" >"$config"
        echo 'owner_enable_config_restored=yes'
        echo 'account_metadata_preserved=yes'
        result=1
    fi
    echo "owner_enable_backup=$backup"
    exit "$result"
}
trap finish EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
armed=yes
# Keep the inode: this file is individually bind-mounted read-only in the app.
cat "$backup/core-config-new.php" >"$config"
docker exec -i --user www-data app_php php <<'PHP'
<?php
declare(strict_types=1);
ini_set('display_errors','0');
try{
    define('ROOT_PATH','/var/www/html');define('APP_PATH',ROOT_PATH.'/app');
    $c=require ROOT_PATH.'/env/env.production.php';$d=$c['db'];
    require APP_PATH.'/helpers/mail_center.php';require APP_PATH.'/helpers/mail_pilot.php';require APP_PATH.'/helpers/session.php';
    $pdo=new PDO('mysql:host='.$d['host'].';dbname='.$d['name'].';charset=utf8mb4',$d['user'],$d['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $owner=$c['mail_center']['owner_user_id'];
    if(($c['mail_center']['enabled']??false)!==true||($c['mail_center']['oauth_log_redaction_verified']??false)!==true||mailCenterOwnerId($pdo,$c,['core_user'=>['id'=>$owner]])!==$owner||mailCenterOwnerId($pdo,$c,[])!==null||mailCenterOwnerId($pdo,$c,['core_user'=>['id'=>$owner+1]])!==null)throw new RuntimeException();
    foreach(['gmail','zoho']as$provider)if(!mailPilotReady($pdo,$c,$provider))throw new RuntimeException();
    coreConfigureSession($c);MailPilotConfig::requireHttps($c,['HTTPS'=>'on','HTTP_HOST'=>'meuprojetoweb.com']);
    $s=$pdo->prepare('SELECT COUNT(*) FROM core_mail_connections WHERE owner_user_id=? AND token_envelope IS NOT NULL');$s->execute([$owner]);
    echo 'owner_only_access_checks=PASS'.PHP_EOL;
    echo 'gmail_zoho_integration_ready=yes'.PHP_EOL;
    echo 'production_connections_count='.(int)$s->fetchColumn().PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,"Validação da habilitação falhou; configuração será restaurada.\n");exit(1);}
PHP
after_secret=$(sha256sum "$private/secrets.json");after_secret=${after_secret%% *}
[[ "$before_secret" == "$after_secret" ]] || exit 1
sleep 3
status=$(curl --silent --show-error --max-time 20 -o /dev/null -w '%{http_code}' https://meuprojetoweb.com/web/admin/central_contas/unified.php)
[[ "$status" == 404 ]] || exit 1
status=$(curl --silent --show-error --max-time 20 -o /dev/null -w '%{http_code}' https://meuprojetoweb.com/web/admin/login.php)
[[ "$status" == 200 ]] || exit 1
completed=yes
echo 'mail_center_enabled_for_owner=yes'
echo 'anonymous_access_denied=yes'
echo 'private_secrets_unchanged=yes'
echo 'containers_restarted=no'
echo 'emails_sent=no'
