#!/usr/bin/env bash
set -euo pipefail
umask 077
[[ "${2:-}" == --publish-organization && "${1:-}" =~ ^[a-f0-9]{64}$ ]] || exit 1
private=/opt/private-mail-center
root=/opt/workspace
config="$private/core-config.php"
[[ -d "$private/releases" && -f "$config" && ! -L "$config" ]] || exit 1
exec 9>"$private/mail-release.lock"
flock -n 9 || exit 1
backup=$(mktemp -d "$private/releases/organization-$(date -u +%Y%m%dT%H%M%SZ)-XXXXXXXX")
echo "organization_backup=$backup"
cat >"$backup/package.tar"
[[ "$(sha256sum "$backup/package.tar" | cut -d' ' -f1)" == "$1" ]] || exit 1
mkdir "$backup/stage"
tar -xf "$backup/package.tar" -C "$backup/stage"
stage="$backup/stage"
image=$(docker inspect --format '{{.Image}}' app_php)
[[ "$image" =~ ^sha256:[a-f0-9]{64}$ ]] || exit 1
check_files(){ docker run --rm --network none --read-only --mount "type=bind,source=$stage,target=/release,readonly" --mount "type=bind,source=$root,target=/production,readonly" --entrypoint php "$image" /release/check.php /release /production "$1"; }
check_files --preflight >"$backup/files.txt"
echo 'production_baselines=PASS'
while IFS= read -r path; do
    if [[ "$path" == *.php ]]; then docker exec -i app_php php -l <"$stage/$path" >"$backup/lint.txt"; fi
done <"$backup/files.txt"
echo 'release_php_syntax=PASS'
cat "$stage/status.php" | docker exec -i --user www-data app_php php /dev/stdin --status-only
: >"$backup/existing.txt"; : >"$backup/new.txt"
while IFS= read -r path; do
    [[ "$(realpath -m "$root/$path")" == "$root/"* ]] || exit 1
    if [[ -f "$root/$path" ]]; then printf '%s\n' "$path" >>"$backup/existing.txt"; else printf '%s\n' "$path" >>"$backup/new.txt"; fi
done <"$backup/files.txt"
tar -czf "$backup/code-before.tar.gz" -C "$root" -T "$backup/existing.txt"
gzip -t "$backup/code-before.tar.gz"
cp -a "$config" "$backup/config-before.php"
before_secret=$(sha256sum "$private/secrets.json");before_secret=${before_secret%% *}
new_config=$(docker exec app_php php -r '$c=require "/run/mail-center/core-config.php";if(($c["enabled"]??false)!==true||($c["oauth_log_redaction_verified"]??false)!==true)exit(1);$c["remote_organization_enabled"]=true;echo base64_encode("<?php\nreturn ".var_export($c,true).";\n");')
printf '%s' "$new_config" | base64 -d >"$backup/config-after.php"
docker exec -i app_php php -l <"$backup/config-after.php" >"$backup/config-lint.txt"
armed=no
completed=no
finish(){
    result=$?
    trap - EXIT INT TERM
    if [[ "$armed" == yes && "$completed" != yes ]]; then
        cat "$backup/config-before.php" >"$config"
        tar -xzf "$backup/code-before.tar.gz" -C "$root"
        while IFS= read -r path; do [[ "$(realpath -m "$root/$path")" == "$root/"* && ! -L "$root/$path" ]] && rm -f -- "$root/$path"; done <"$backup/new.txt"
        docker exec app_php apache2ctl graceful >"$backup/rollback-reload.txt" 2>&1 || true
        echo 'organization_rollback=yes'
        result=1
    fi
    echo "organization_backup=$backup"
    exit "$result"
}
trap finish EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
check_files --preflight >"$backup/files-rechecked.txt"
cmp -s "$config" "$backup/config-before.php"
armed=yes
while IFS= read -r path; do
    install -d -m 0755 "$(dirname "$root/$path")"
    install -m 0644 "$stage/$path" "$root/$path.mail-organization-new"
    mv -f -- "$root/$path.mail-organization-new" "$root/$path"
done <"$backup/files.txt"
# Preserve the inode of the individually bind-mounted private configuration.
cat "$backup/config-after.php" >"$config"
check_files --after >"$backup/files-after.txt"
docker exec app_php php /var/www/html/app/console/check_mail_center.php --organization >"$backup/schema.txt"
docker exec app_php apache2ctl graceful >"$backup/reload.txt" 2>&1
sleep 2
[[ "$(curl --silent --show-error --max-time 20 -o /dev/null -w '%{http_code}' https://meuprojetoweb.com/web/admin/login.php)" == 200 ]] || exit 1
[[ "$(curl --silent --show-error --max-time 20 -o /dev/null -w '%{http_code}' https://meuprojetoweb.com/web/admin/central_contas/unified.php)" == 404 ]] || exit 1
after_secret=$(sha256sum "$private/secrets.json");after_secret=${after_secret%% *}
[[ "$before_secret" == "$after_secret" && "$(docker inspect --format '{{.Image}}' app_php)" == "$image" ]] || exit 1
completed=yes
echo 'organization_published=yes'
echo 'production_code_hashes=PASS'
echo 'core_health_and_anonymous_restriction=PASS'
echo 'private_secrets_unchanged=yes'
echo 'database_migrations=no'
echo 'application_image_unchanged=yes'
echo 'container_recreation=no'
# Sync failure is reported separately; it never triggers code rollback or retry.
if ! cat "$stage/status.php" | docker exec -i --user www-data app_php php /dev/stdin --sync-limited; then echo 'metadata_validation=pending'; fi
exit 0
