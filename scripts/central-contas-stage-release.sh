#!/usr/bin/env bash
set -euo pipefail
umask 077
expected=${1:?Expected SHA256 required}
[[ "$expected" =~ ^[a-f0-9]{64}$ ]] || exit 1
private=/opt/private-mail-center
[[ -d "$private" && ! -L "$private" ]] || exit 1
[[ ! -L "$private/releases" ]] || exit 1
install -d -m 0700 "$private/releases"
stage=$(mktemp -d "$private/releases/plan-$(date -u +%Y%m%dT%H%M%SZ)-XXXXXXXX")
cat >"$stage/overlay.tar"
actual=$(sha256sum "$stage/overlay.tar");actual=${actual%% *}
[[ "$actual" == "$expected" ]] || exit 1
while IFS= read -r entry; do
    [[ "$entry" != /* && "$entry" != *'../'* && "$entry" != '..' && "$entry" != *'\'* ]] || exit 1
done < <(tar -tf "$stage/overlay.tar")
mkdir "$stage/files"
tar -xf "$stage/overlay.tar" -C "$stage/files" --no-same-owner --no-same-permissions
[[ -z "$(find "$stage/files" -type l -print -quit)" ]] || exit 1
image=$(docker inspect --format '{{.Image}}' app_php)
[[ "$image" =~ ^sha256:[a-f0-9]{64}$ ]] || exit 1
echo "mail_overlay_staged=$stage"
docker run --rm --network none --mount "type=bind,source=$stage/files,target=/release,readonly" --mount 'type=bind,source=/opt/workspace,target=/production,readonly' --entrypoint php "$image" /release/app/console/check_mail_overlay.php --root=/release --target=/production
docker run --rm --network none --mount "type=bind,source=$stage/files,target=/release,readonly" --entrypoint php "$image" /release/app/console/check_mail_release.php --root=/release
printf '%s\n' "$image" >"$stage/image-id"
printf '%s\n' "$expected" >"$stage/archive-sha256"
echo 'mail_overlay_plan=PASS'
echo 'application_files_changed=no'
echo 'migrations_applied=no'
echo 'containers_restarted=no'
echo 'mail_center_enabled=no'
