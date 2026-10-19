#!/usr/bin/env bash
# Preparatory backup only. Does not deploy, migrate, connect mailboxes or send mail.
set -euo pipefail
umask 077
workspace=${1:-/opt/workspace}
private=${2:-/opt/private-mail-center}
backup_root=/opt/backups/central-email
[[ "$workspace" == /opt/* && "$workspace" != *'/../'* && -d "$workspace/app" ]] || { echo 'Workspace inválido.' >&2; exit 1; }
[[ "$private" == /opt/* && "$private" != *'/../'* && "$private" != "$workspace" && "$private" != "$workspace/"* ]] || { echo 'Pasta privada inválida.' >&2; exit 1; }
mkdir -p "$backup_root"
chmod 700 "$backup_root"
backup=$(mktemp -d "$backup_root/before-mail-$(date -u +%Y%m%dT%H%M%SZ)-XXXXXX")
chmod 700 "$backup"
docker exec app_db sh -c 'export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"; exec mysqldump -uroot --single-transaction --routines --events --triggers --hex-blob --set-gtid-purged=OFF --databases core' | gzip > "$backup/core.sql.gz"
gzip -t "$backup/core.sql.gz"
tar --acls --xattrs --numeric-owner --exclude='./_deploy' --exclude='./.git' --exclude='./storage/cache' --exclude='./storage/logs' -czf "$backup/core-files.tar.gz" -C "$workspace" .
tar -tzf "$backup/core-files.tar.gz" >/dev/null
if [[ -d "$private" ]]; then
    tar --acls --xattrs --numeric-owner -czf "$backup/mail-private.tar.gz" -C "$private" .
    tar -tzf "$backup/mail-private.tar.gz" >/dev/null
fi
(cd "$backup" && sha256sum ./*.gz > SHA256SUMS && sha256sum --check SHA256SUMS >/dev/null)
chmod 600 "$backup"/*
printf 'Backup protegido criado: %s\n' "$backup"
printf 'Integridade dos arquivos conferida. Restauração ainda precisa ser validada em ambiente isolado.\n'
