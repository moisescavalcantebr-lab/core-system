#!/usr/bin/env bash
set -euo pipefail
umask 077
# Receives only the prepared secrets.json on stdin, over authenticated SSH.
expected=${1:?Expected SHA256 required}
[[ "$expected" =~ ^[a-f0-9]{64}$ ]] || exit 1
private=/opt/private-mail-center
[[ ! -L "$private" ]] || { printf 'Pasta privada não pode ser link simbólico.\n' >&2; exit 1; }
uid=$(docker exec --user www-data app_php id -u)
gid=$(docker exec --user www-data app_php id -g)
[[ "$uid" =~ ^[1-9][0-9]*$ && "$gid" =~ ^[1-9][0-9]*$ ]] || exit 1
install -d -m 0750 -o root -g "$gid" "$private"
[[ "$(realpath "$private")" == "$private" ]] || exit 1
[[ ! -L "$private/attachments" && ! -L "$private/secrets.json" && ! -L "$private/core-config.php" ]] || exit 1
tmp=$(mktemp "$private/.incoming-XXXXXXXX")
trap 'rm -f "$tmp"' EXIT
cat > "$tmp"
actual=$(sha256sum "$tmp");actual=${actual%% *}
[[ "$actual" == "$expected" ]] || { printf 'Hash privado divergente.\n' >&2; exit 1; }
if [[ -e "$private/secrets.json" ]]; then
    existing=$(sha256sum "$private/secrets.json");existing=${existing%% *}
    [[ "$existing" == "$expected" ]] || { printf 'Arquivo privado existente diferente; preservado sem sobrescrever.\n' >&2; exit 1; }
else
    chown "$uid:$gid" "$tmp"
    chmod 0600 "$tmp"
    mv "$tmp" "$private/secrets.json"
fi
install -d -m 0700 -o "$uid" -g "$gid" "$private/attachments"
fragment=$(docker exec -i app_php php <<'PHP'
<?php
ini_set('display_errors','0');
try {
    $config=require '/var/www/html/env/env.production.php';$db=$config['db'];
    $pdo=new PDO('mysql:host='.$db['host'].';dbname='.$db['name'].';charset=utf8mb4',$db['user'],$db['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $s=$pdo->prepare("SELECT id FROM core_users WHERE LOWER(email)=LOWER(?) AND status=1 AND role IN ('ADMIN','SUPER_ADMIN')");$s->execute(['moises.cavalcante@meuprojetoweb.com']);$rows=$s->fetchAll(PDO::FETCH_COLUMN);
    if(count($rows)!==1)exit(1);
    $fragment=['enabled'=>false,'owner_user_id'=>(int)$rows[0],'secrets_file'=>'/run/mail-center/secrets.json','oauth_log_redaction_verified'=>false,'trusted_proxy_ips'=>[]];
    echo "<?php\nreturn ".var_export($fragment,true).";\n";
} catch(Throwable $e) {exit(1);}
PHP
)
if [[ -e "$private/core-config.php" ]]; then
    [[ "$(cat "$private/core-config.php")" == "$fragment" ]] || { printf 'Fragmento privado diferente; preservado.\n' >&2; exit 1; }
else
    printf '%s\n' "$fragment" > "$private/core-config.php"
    chown root:"$gid" "$private/core-config.php"
    chmod 0640 "$private/core-config.php"
fi
printf 'private_secrets_provisioned=yes\nprivate_permissions_restricted=yes\nowner_fragment_resolved=yes\nmodule_enabled=no\ncontainers_restarted=no\ncore_environment_changed=no\n'
