#!/usr/bin/env bash
set -euo pipefail
umask 077
[[ "${1:-}" =~ ^[a-f0-9]{64}$ ]] || exit 1
root=/opt/workspace; private=/opt/private-futebol
install -d -m 0700 "$private"
exec 9>"$private/publication.lock"; flock -n 9 || exit 1
backup=$(mktemp -d "$private/finance-$(date -u +%Y%m%dT%H%M%SZ)-XXXXXXXX")
echo "finance_backup=$backup"
cat >"$backup/package.tar"
[[ "$(sha256sum "$backup/package.tar" | cut -d' ' -f1)" == "$1" ]] || exit 1
mkdir "$backup/stage"; stage="$backup/stage"
tar -xf "$backup/package.tar" -C "$stage"
image=$(docker inspect --format '{{.Image}}' app_php)
dbimage=$(docker inspect --format '{{.Image}}' app_db)
[[ "$image" =~ ^sha256:[a-f0-9]{64}$ && "$dbimage" =~ ^sha256:[a-f0-9]{64}$ ]] || exit 1
check(){ docker run --rm --network none --read-only --mount "type=bind,source=$stage,target=/release,readonly" --mount "type=bind,source=$root,target=/production,readonly" --entrypoint php "$image" /release/check.php /release /production "$1"; }
restore=''; armed=no; completed=no; migrate_file="/tmp/finance-migrate-$(basename "$backup").php"
finish(){
 result=$?; trap - EXIT INT TERM
 if [[ "$armed" == yes && "$completed" != yes ]]; then
  tar -xzf "$backup/code-before.tar.gz" -C "$root"
  while IFS= read -r path; do [[ "$(realpath -m "$root/$path")" == "$root/"* && ! -L "$root/$path" ]] && rm -f -- "$root/$path"; done <"$backup/new.txt"
  docker exec app_php apache2ctl graceful >"$backup/rollback.txt" 2>&1 || true
  echo 'finance_code_rollback=yes'; echo 'finance_additive_tables_retained=yes'; result=1
 fi
 if [[ -f "$backup/maintenance.tsv" ]]; then
  while IFS=$'\t' read -r index target; do
   [[ "$(realpath -m "$root/$target")" == "$root/projects/"* && ! -L "$root/$target" ]] || continue
   if [[ -f "$backup/access-$index.before" ]]; then install -m 0644 "$backup/access-$index.before" "$root/$target"; else rm -f -- "$root/$target"; fi
  done <"$backup/maintenance.tsv"
 fi
 if [[ -n "$restore" ]]; then docker rm -fv "$restore" >/dev/null 2>&1 || true; fi
 docker exec app_php rm -f -- "$migrate_file" >/dev/null 2>&1 || true
 echo "finance_backup=$backup"; exit "$result"
}
trap finish EXIT; trap 'exit 130' INT; trap 'exit 143' TERM
docker cp "$stage/migrate.php" "app_php:$migrate_file"
docker exec app_php php "$migrate_file" --inventory >"$stage/inventory.json"
check --preflight >"$backup/files.tsv"
docker exec -i app_php php -r '$a=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);foreach($a as $i=>$p){if(!preg_match("/^[a-zA-Z0-9_]+$/D",$p["database"]))exit(1);echo $i,"\t",$p["path"],"\t",$p["database"],"\t",$p["tables"],"\t",$p["entries"],PHP_EOL;}' <"$stage/inventory.json" >"$backup/projects.tsv"
[[ -s "$backup/projects.tsv" ]] || exit 1
echo 'finance_baselines=PASS'
while IFS=$'\t' read -r source target; do if [[ "$source" == *.php ]]; then docker exec -i app_php php -l <"$stage/$source" >"$backup/lint.txt"; fi; done <"$backup/files.tsv"
echo 'finance_php_syntax=PASS'
: >"$backup/existing.txt"; : >"$backup/new.txt"
while IFS=$'\t' read -r source target; do
 [[ "$(realpath -m "$root/$target")" == "$root/"* ]] || exit 1
 if [[ -f "$root/$target" ]]; then printf '%s\n' "$target" >>"$backup/existing.txt"; else printf '%s\n' "$target" >>"$backup/new.txt"; fi
done <"$backup/files.tsv"
tar -czf "$backup/code-before.tar.gz" -C "$root" -T "$backup/existing.txt"; gzip -t "$backup/code-before.tar.gz"
# Block financial web actions during the snapshot/migration/copy. The existing access file is restored on every exit.
: >"$backup/maintenance.tsv"
while IFS=$'\t' read -r index project database tables entries; do
 target="$project/web/admin/financeiro/.htaccess"
 [[ ! -L "$root/$target" ]] || exit 1
 if [[ -f "$root/$target" ]]; then cp "$root/$target" "$backup/access-$index.before"; fi
 printf '%s\t%s\n' "$index" "$target" >>"$backup/maintenance.tsv"
 printf 'Require all denied\n' >"$root/$target"
 chmod 0644 "$root/$target"
done <"$backup/projects.tsv"
sleep 3
while IFS=$'\t' read -r index project database tables entries; do
 docker exec app_db sh -c 'export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"; exec mysqldump -uroot --single-transaction --routines --events --triggers --hex-blob --set-gtid-purged=OFF "$1"' sh "$database" | gzip >"$backup/project-$index.sql.gz"
 gzip -t "$backup/project-$index.sql.gz"
 # Validate the complete dump in a network-isolated database container before modifying the project.
 restore="ff-restore-$(date +%s)-$index"
 docker run -d --network none --name "$restore" -e MYSQL_ALLOW_EMPTY_PASSWORD=yes "$dbimage" --skip-log-bin --event-scheduler=OFF --performance-schema=OFF --innodb-buffer-pool-size=64M >"$backup/restore-container-$index.txt"
 ready=no
 for attempt in $(seq 1 60); do if docker exec "$restore" mysqladmin --host=127.0.0.1 -uroot ping --silent >/dev/null 2>&1; then ready=yes; break; fi; sleep 1; done
 [[ "$ready" == yes ]] || { echo 'finance_restore_not_ready'; exit 1; }
 docker exec "$restore" mysql -uroot -e 'CREATE DATABASE restore_test CHARACTER SET utf8mb4'
 gzip -dc "$backup/project-$index.sql.gz" | docker exec -i "$restore" mysql -uroot restore_test >"$backup/restore-$index.txt" 2>&1
 [[ "$(docker exec "$restore" mysql -uroot -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='restore_test' AND table_type='BASE TABLE'")" == "$tables" ]] || exit 1
 [[ "$(docker exec "$restore" mysql -uroot -N -e 'SELECT COUNT(*) FROM restore_test.finance_entries')" == "$entries" ]] || exit 1
 docker rm -fv "$restore" >/dev/null; restore=''
 docker exec app_php php "$migrate_file" --snapshot "$project" >"$backup/data-$index.before"
 echo "finance_backup_restore_$index=PASS"
done <"$backup/projects.tsv"
check --preflight >"$backup/rechecked.tsv"; cmp "$backup/files.tsv" "$backup/rechecked.tsv"
while IFS=$'\t' read -r index project database tables entries; do
 docker exec -i app_php php "$migrate_file" --migrate "$project" <"$stage/modules/financeiro_futebol/database/schema.sql"
 docker exec app_php php "$migrate_file" --snapshot "$project" >"$backup/data-$index.after"
 cmp "$backup/data-$index.before" "$backup/data-$index.after"
done <"$backup/projects.tsv"
echo 'finance_existing_records_unchanged=PASS'
armed=yes
# Enable the new addon only after all its helpers, routes and database tables exist.
for phase in code manifests; do
 while IFS=$'\t' read -r source target; do
  manifest=no; [[ "$source" != */module.json ]] || manifest=yes
  if [[ "$phase" == code && "$manifest" == yes || "$phase" == manifests && "$manifest" != yes ]]; then continue; fi
  install -d -m 0755 "$(dirname "$root/$target")"
  install -m 0644 "$stage/$source" "$root/$target.finance-new"; mv -f -- "$root/$target.finance-new" "$root/$target"
 done <"$backup/files.tsv"
done
check --after >"$backup/after.tsv"
docker exec app_php apache2ctl configtest >"$backup/apache-config.txt" 2>&1
docker exec app_php apache2ctl graceful >"$backup/reload.txt" 2>&1
[[ "$(curl --silent --show-error --max-time 20 -o /dev/null -w '%{http_code}' https://meuprojetoweb.com/web/admin/login.php)" == 200 ]] || exit 1
[[ "$(curl --silent --show-error --max-time 20 -o /dev/null -w '%{http_code}' https://meuprojetoweb.com/web/admin/central_contas/unified.php)" == 404 ]] || exit 1
[[ "$(docker inspect --format '{{.Image}}' app_php)" == "$image" ]] || exit 1
while IFS=$'\t' read -r index target; do
 if [[ -f "$backup/access-$index.before" ]]; then install -m 0644 "$backup/access-$index.before" "$root/$target"; else rm -f -- "$root/$target"; fi
done <"$backup/maintenance.tsv"
: >"$backup/maintenance.tsv"
while IFS=$'\t' read -r index project database tables entries; do
 [[ "$(curl --silent --show-error --max-time 20 -o /dev/null -w '%{http_code}' "https://meuprojetoweb.com/$project/web/admin/financeiro/time_elenco.php")" == 302 ]] || exit 1
done <"$backup/projects.tsv"
completed=yes
echo 'finance_published=yes'; echo 'finance_hashes_and_core_health=PASS'
printf 'finance_projects_updated='; wc -l <"$backup/projects.tsv"
echo 'finance_email_and_project_settings_modified=no'
