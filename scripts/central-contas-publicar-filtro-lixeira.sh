#!/usr/bin/env bash
set -euo pipefail
umask 077
[[ "${1:-}" == --publish-approved-trash-filter ]] || exit 1
private=/opt/private-mail-center
target=/opt/workspace/app/services/mail/MailMessageService.php
[[ -d "$private/releases" && -f "$target" && ! -L "$target" ]] || exit 1
[[ "$(realpath "$target")" == "$target" ]] || exit 1
exec 9>"$private/mail-release.lock"
flock -n 9 || exit 1
normalized_hash(){ docker exec app_php php -r 'echo hash("sha256",str_replace("\r\n","\n",file_get_contents("/var/www/html/app/services/mail/MailMessageService.php")));'; }
before=__BEFORE_HASH__
after=__AFTER_HASH__
current=$(normalized_hash)
[[ "$current" == "$before" || "$current" == "$after" ]] || { echo 'production_file_conflict=yes'; exit 1; }
backup=$(mktemp -d "$private/releases/trash-filter-$(date -u +%Y%m%dT%H%M%SZ)-XXXXXXXX")
cp -a "$target" "$backup/before.php"
printf '%s' '__SOURCE_GZIP_BASE64__' | base64 -d | gzip -d >"$backup/after.php"
[[ "$(sha256sum "$backup/after.php" | cut -d' ' -f1)" == "$after" ]] || exit 1
docker exec -i app_php php -l <"$backup/after.php"
armed=no
completed=no
finish(){
    result=$?
    trap - EXIT INT TERM
    if [[ "$armed" == yes && "$completed" != yes ]]; then
        cp -a "$backup/before.php" "$target.trash-rollback"
        mv -f -- "$target.trash-rollback" "$target"
        docker exec app_php apache2ctl graceful >"$backup/rollback-reload.log" 2>&1 || true
        echo 'trash_filter_rolled_back=yes'
        result=1
    fi
    echo "trash_filter_backup=$backup"
    exit "$result"
}
trap finish EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
armed=yes
cp -a "$target" "$target.trash-new"
cat "$backup/after.php" >"$target.trash-new"
mv -f -- "$target.trash-new" "$target"
[[ "$(normalized_hash)" == "$after" ]] || exit 1
docker exec app_php apache2ctl graceful >"$backup/reload.log" 2>&1
docker exec app_php php /var/www/html/app/console/check_mail_center.php --organization >"$backup/schema-check.txt"
sleep 2
[[ "$(curl --silent --show-error --max-time 20 -o /dev/null -w '%{http_code}' https://meuprojetoweb.com/web/admin/login.php)" == 200 ]] || exit 1
[[ "$(curl --silent --show-error --max-time 20 -o /dev/null -w '%{http_code}' https://meuprojetoweb.com/web/admin/central_contas/unified.php)" == 404 ]] || exit 1
completed=yes
echo 'trash_filter_published=yes'
echo 'production_code_hash=PASS'
echo 'core_health_and_anonymous_restriction=PASS'
echo 'database_changes=no'
echo 'oauth_permissions_changed=no'
echo 'provider_calls=no'
echo 'containers_restarted=no'
# Stop before any CRLF terminator appended by the PowerShell stdin pipeline.
exit 0
