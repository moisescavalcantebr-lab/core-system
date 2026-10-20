#!/usr/bin/env bash
set -euo pipefail
umask 077
[[ "${1:-}" =~ ^[a-f0-9]{64}$ ]] || exit 1
root=/opt/workspace
private=/opt/private-futebol
install -d -m 0700 "$private"
exec 9>"$private/publication.lock"
flock -n 9 || exit 1
backup=$(mktemp -d "$private/release-$(date -u +%Y%m%dT%H%M%SZ)-XXXXXXXX")
echo "football_backup=$backup"
cat >"$backup/package.tar"
[[ "$(sha256sum "$backup/package.tar" | cut -d' ' -f1)" == "$1" ]] || exit 1
mkdir "$backup/stage"
stage="$backup/stage"
tar -xf "$backup/package.tar" -C "$stage"
cat "$stage/check.php" | docker exec -i app_php php /dev/stdin --projects >"$stage/projects.json"
image=$(docker inspect --format '{{.Image}}' app_php)
[[ "$image" =~ ^sha256:[a-f0-9]{64}$ ]] || exit 1
check(){ docker run --rm --network none --read-only --mount "type=bind,source=$stage,target=/release,readonly" --mount "type=bind,source=$root,target=/production,readonly" --entrypoint php "$image" /release/check.php /release /production "$1"; }
check --preflight >"$backup/files.tsv"
echo 'football_baselines=PASS'
: >"$backup/avatar-urls.txt"
if grep -q $'\.htaccess\t.htaccess' "$backup/files.tsv"; then
 cat "$stage/check.php" | docker exec -i --user www-data app_php php /dev/stdin --avatar-urls >"$backup/avatar-urls.txt"
 while IFS= read -r avatar; do curl --silent --show-error --max-time 20 -o /dev/null -w '%{http_code}\n' "https://meuprojetoweb.com$avatar" >>"$backup/avatar-status-before.txt"; done <"$backup/avatar-urls.txt"
fi
while IFS=$'\t' read -r source target; do if [[ "$source" == *.php ]]; then docker exec -i app_php php -l <"$stage/$source" >"$backup/lint.txt"; fi; done <"$backup/files.tsv"
echo 'football_php_syntax=PASS'
: >"$backup/existing.txt"; : >"$backup/new.txt"
while IFS=$'\t' read -r source target; do
  [[ "$(realpath -m "$root/$target")" == "$root/"* ]] || exit 1
  if [[ -f "$root/$target" ]]; then printf '%s\n' "$target" >>"$backup/existing.txt"; else printf '%s\n' "$target" >>"$backup/new.txt"; fi
done <"$backup/files.tsv"
tar -czf "$backup/code-before.tar.gz" -C "$root" -T "$backup/existing.txt"
gzip -t "$backup/code-before.tar.gz"
armed=no; completed=no
finish(){
 result=$?; trap - EXIT INT TERM
 if [[ "$armed" == yes && "$completed" != yes ]]; then
   tar -xzf "$backup/code-before.tar.gz" -C "$root"
   while IFS= read -r path; do [[ "$(realpath -m "$root/$path")" == "$root/"* && ! -L "$root/$path" ]] && rm -f -- "$root/$path"; done <"$backup/new.txt"
   docker exec app_php apache2ctl graceful >"$backup/rollback.txt" 2>&1 || true
   echo 'football_rollback=yes'; result=1
 fi
 echo "football_backup=$backup"; exit "$result"
}
trap finish EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
check --preflight >"$backup/rechecked.tsv"
cmp "$backup/files.tsv" "$backup/rechecked.tsv"
armed=yes
# Dependencies must exist before the templates and bootstrap require them.
for phase in dependencies templates; do
 while IFS=$'\t' read -r source target; do
   dependency=no
   [[ "$source" != */appearance.php && "$source" != */football_plan.php ]] || dependency=yes
   if [[ "$phase" == dependencies && "$dependency" != yes || "$phase" == templates && "$dependency" == yes ]]; then continue; fi
   install -d -m 0755 "$(dirname "$root/$target")"
   install -m 0644 "$stage/$source" "$root/$target.futebol-new"
   mv -f -- "$root/$target.futebol-new" "$root/$target"
 done <"$backup/files.tsv"
done
check --after >"$backup/after.tsv"
docker exec app_php apache2ctl graceful >"$backup/reload.txt" 2>&1
docker exec app_php apache2ctl configtest >"$backup/apache-config.txt" 2>&1
sleep 2
[[ "$(curl --silent --show-error --max-time 20 -o /dev/null -w '%{http_code}' https://meuprojetoweb.com/web/admin/login.php)" == 200 ]] || exit 1
[[ "$(curl --silent --show-error --max-time 20 -o /dev/null -w '%{http_code}' https://meuprojetoweb.com/web/admin/central_contas/unified.php)" == 404 ]] || exit 1
[[ "$(docker inspect --format '{{.Image}}' app_php)" == "$image" ]] || exit 1
if grep -q $'\.htaccess\t.htaccess' "$backup/files.tsv"; then
 for blocked in env/env.production.php projects/avatar-delivery-check/web/storage/private.txt projects/avatar-delivery-check/web/storage/uploads/avatars/test.php; do
   [[ "$(curl --silent --show-error --max-time 20 -o /dev/null -w '%{http_code}' "https://meuprojetoweb.com/$blocked")" == 403 ]] || exit 1
 done
 avatar_count=0
 while IFS= read -r avatar; do
   [[ "$(curl --silent --show-error --max-time 20 -o /dev/null -w '%{http_code}' "https://meuprojetoweb.com$avatar")" == 200 ]] || exit 1
   avatar_count=$((avatar_count+1))
 done <"$backup/avatar-urls.txt"
 echo "public_avatar_images_verified=$avatar_count"
 echo 'private_storage_still_blocked=PASS'
fi
completed=yes
echo 'football_published=yes'
echo 'football_hashes_and_core_health=PASS'
printf 'football_projects_updated='
docker exec -i app_php php -r '$a=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);echo count($a),PHP_EOL;' <"$stage/projects.json"
echo 'database_and_project_settings_modified=no'
