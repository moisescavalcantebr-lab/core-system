#!/usr/bin/env bash
set -euo pipefail
umask 077
plan=${1:?Private staged plan required}
[[ "${2:-}" == --install-disabled && "$plan" =~ ^/opt/private-mail-center/releases/plan-[a-zA-Z0-9-]+$ && ! -L "$plan" ]] || exit 1
private=/opt/private-mail-center
root=/opt/workspace
stage="$plan/files"
image=$(cat "$plan/image-id")
[[ "$image" =~ ^sha256:[a-f0-9]{64}$ && "$(docker inspect --format '{{.Image}}' app_php)" == "$image" ]] || exit 1
exec 9>"$private/mail-release.lock"
flock -n 9 || exit 1
override="$root/docker/docker-compose.mail-center.prod.yml"
[[ ! -e "$override" && ! -L "$override" && ! -e "$private/core-env-base.php" ]] || { echo 'Integração já existe; revisar antes de repetir.' >&2; exit 1; }
docker run --rm --network none --mount "type=bind,source=$stage,target=/release,readonly" --mount "type=bind,source=$root,target=/production,readonly" --entrypoint php "$image" /release/app/console/check_mail_overlay.php --root=/release --target=/production
docker run --rm --network none --mount "type=bind,source=$stage,target=/release,readonly" --entrypoint php "$image" /release/app/console/check_mail_release.php --root=/release
# A static original ENV can be mounted outside the webroot without changing relative paths.
docker exec app_php php -r '$t=file_get_contents("/var/www/html/env/env.production.php");if(preg_match("/__DIR__|__FILE__|dirname\\s*\\(/",$t))exit(1);'
gid=$(docker exec --user www-data app_php id -g)
[[ "$gid" =~ ^[1-9][0-9]*$ ]] || exit 1
backup=$(mktemp -d "$private/releases/install-$(date -u +%Y%m%dT%H%M%SZ)-XXXXXXXX")
cd "$root/docker"
compose=(docker compose -p docker -f docker-compose.yml -f docker-compose.prod.yml)
tls="$private/tls/docker-compose.origin.yml"
[[ -f "$tls" && ! -L "$tls" ]] || exit 1
"${compose[@]}" -f "$tls" config --format json >"$backup/compose-before.json"
docker inspect app_php >"$backup/container-before.json"
cp -a "$root/env/env.production.php" "$backup/environment-before.php"
docker exec app_db sh -c 'export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"; exec mysqldump -uroot --single-transaction --routines --events --triggers --hex-blob --set-gtid-purged=OFF --databases core' | gzip >"$backup/core-before.sql.gz"
gzip -t "$backup/core-before.sql.gz"
docker run --rm --network none --mount "type=bind,source=$stage,target=/release,readonly" --entrypoint php "$image" -r '$m=json_decode(file_get_contents("/release/mail-overlay.json"),true,512,JSON_THROW_ON_ERROR);foreach($m["files"]as$f)echo $f["path"],PHP_EOL;' >"$backup/files.txt"
: >"$backup/existing.txt"; : >"$backup/new.txt"
while IFS= read -r path; do
    [[ "$(realpath -m "$root/$path")" == "$root/"* && ! -L "$root/$path" ]] || exit 1
    if [[ -f "$root/$path" ]]; then printf '%s\n' "$path" >>"$backup/existing.txt"; else printf '%s\n' "$path" >>"$backup/new.txt"; fi
done <"$backup/files.txt"
tar -czf "$backup/affected-before.tar.gz" -C "$root" -T "$backup/existing.txt"
tar -tzf "$backup/affected-before.tar.gz" >/dev/null
install -m 0640 -o root -g "$gid" "$backup/environment-before.php" "$backup/base-new.php"
cat >"$backup/environment-new.php" <<'PHP'
<?php
declare(strict_types=1);
$config = require '/run/mail-center/core-env-base.php';
$config['app']['environment'] = 'production';
$config['app']['session_secure'] = true;
$config['mail_center'] = require '/run/mail-center/core-config.php';
return $config;
PHP
cat >"$backup/override-new.yml" <<YAML
services:
  app:
    volumes:
      - "$private/secrets.json:/run/mail-center/secrets.json:ro"
      - "$private/core-config.php:/run/mail-center/core-config.php:ro"
      - "$private/core-env-base.php:/run/mail-center/core-env-base.php:ro"
      - "$private/attachments:/var/lib/mail-center/attachments"
YAML
armed=no
completed=no
finish(){
    result=$?
    trap - EXIT INT TERM
    rm -f -- "$backup/headers"
    if [[ "$armed" == yes && "$completed" != yes ]]; then
        echo 'mail_install_failed=yes'
        while IFS= read -r path; do rm -f -- "$root/$path"; done <"$backup/new.txt"
        tar -xzf "$backup/affected-before.tar.gz" -C "$root"
        cp -a "$backup/environment-before.php" "$root/env/env.production.php"
        rm -f -- "$override"
        if docker compose -p docker --project-directory "$root/docker" -f "$backup/compose-before.json" up -d --no-build --no-deps --force-recreate app >"$backup/rollback.log" 2>&1; then
            echo 'mail_code_and_container_rollback=yes'
            rm -f -- "$private/core-env-base.php"
        else echo 'mail_rollback_needs_attention=yes'; fi
        echo 'additive_mail_tables_preserved=yes'
        result=1
    fi
    echo "mail_install_backup=$backup"
    exit "$result"
}
trap finish EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
armed=yes
install -m 0640 -o root -g "$gid" "$backup/base-new.php" "$private/core-env-base.php"
install -m 0600 "$backup/override-new.yml" "$override"
"${compose[@]}" -f "$override" -f "$tls" config --quiet >"$backup/compose-validation.log" 2>&1
# Install dependencies and additions before changing shared bootstrap/layout files.
for pass in additions shared; do
    while IFS= read -r path; do
        shared=no
        case "$path" in .htaccess|app/bootstrap/bootstrap.php|app/services/auth/AuthService.php|app/views/layout_admin.php|app/views/partials/header.php|app/views/partials/sidebar.php) shared=yes;; esac
        if [[ "$pass" == shared && "$shared" != yes || "$pass" == additions && "$shared" == yes ]]; then continue; fi
        install -d -m 0755 "$(dirname "$root/$path")"
        install -m 0644 "$stage/$path" "$root/$path.mail-new"
        mv -f -- "$root/$path.mail-new" "$root/$path"
    done <"$backup/files.txt"
done
"${compose[@]}" -f "$override" -f "$tls" up -d --no-build --no-deps --force-recreate app >"$backup/recreate.log" 2>&1
install -m 0644 "$backup/environment-new.php" "$root/env/env.production.php.mail-new"
mv -f -- "$root/env/env.production.php.mail-new" "$root/env/env.production.php"
docker exec app_php php -r '$c=require "/var/www/html/env/env.production.php";if(($c["mail_center"]["enabled"]??true)!==false||($c["mail_center"]["oauth_log_redaction_verified"]??true)!==false)exit(1);echo "mail_private_config_loaded_disabled=yes\n";'
docker exec app_php php /var/www/html/app/console/migrate_mail_center.php --organization
docker exec app_php php /var/www/html/app/console/migrate_mail_center.php --organization --apply
docker exec app_php php /var/www/html/app/console/check_mail_center.php --organization
docker exec app_php php /var/www/html/app/console/check_mail_release.php --root=/var/www/html --installed
ca="$private/tls/release-ff1eb07b4c61e61a22331078097b44fed9e85236fad65606065bf15fdf2bf22e/cloudflare-origin-ca-rsa.pem"
healthy=no
for attempt in {1..20}; do
    status=$(curl -s --noproxy '*' --max-time 5 --cacert "$ca" --resolve meuprojetoweb.com:443:127.0.0.1 -D "$backup/headers" -o /dev/null -w '%{http_code}' https://meuprojetoweb.com/web/admin/login.php || true)
    if [[ "$status" == 200 ]]; then healthy=yes; break; fi
    sleep 1
done
[[ "$healthy" == yes ]] || exit 1
for attribute in 'secure' 'httponly' 'samesite=lax'; do
    grep -i '^set-cookie:' "$backup/headers" | grep -Eqi ";[[:space:]]*$attribute([;[:space:]]|$)" || exit 1
done
public_status=$(curl --silent --show-error --max-time 20 -o /dev/null -w '%{http_code}' https://meuprojetoweb.com/web/admin/login.php)
[[ "$public_status" == 200 ]] || exit 1
completed=yes
echo 'mail_code_installed=yes'
echo 'mail_migrations_001_004=PASS'
echo 'mail_private_mounts=yes'
echo 'admin_login_cookie_secure_httponly_samesite=PASS'
echo 'core_public_healthy=yes'
echo 'other_module_migrations=not_run'
echo 'application_image_unchanged=yes'
echo 'mail_center_enabled=no'
echo 'real_oauth_connections=not_created'
