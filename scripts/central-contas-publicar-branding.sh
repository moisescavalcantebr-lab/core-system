#!/usr/bin/env bash
set -euo pipefail
umask 077
expected=${1:?Expected archive SHA256 required}
[[ "$expected" =~ ^[a-f0-9]{64}$ ]] || exit 1
private=/opt/private-mail-center
target=/opt/workspace/web/central-email
[[ -d "$private" && ! -L "$private" ]] || exit 1
[[ "$(realpath /opt/workspace/web)" == /opt/workspace/web && ! -L "$target" ]] || exit 1
exec 9>"$private/branding-publication.lock"
flock -n 9 || exit 1
stage=$(mktemp -d "$private/branding-$(date -u +%Y%m%dT%H%M%SZ)-XXXXXXXX")
cat >"$stage/bundle.tar"
actual=$(sha256sum "$stage/bundle.tar");actual=${actual%% *}
[[ "$actual" == "$expected" ]] || exit 1
[[ "$(tar -tf "$stage/bundle.tar" | sort)" == "$(printf '%s\n' index.html privacidade.html termos.html site.css | sort)" ]] || exit 1
mkdir "$stage/new"
tar -xf "$stage/bundle.tar" -C "$stage/new" --no-same-owner --no-same-permissions
for name in index.html privacidade.html termos.html site.css; do
    [[ -f "$stage/new/$name" && ! -L "$stage/new/$name" ]] || exit 1
done
(cd "$stage/new" && sha256sum index.html privacidade.html termos.html site.css) >"$stage/expected.sha256"
chmod 0755 "$stage/new"
chmod 0644 "$stage/new/"*
had_previous=no
armed=no
completed=no
finish(){
    result=$?
    trap - EXIT INT TERM
    if [[ "$armed" == yes && "$completed" != yes ]]; then
        [[ ! -e "$target" ]] || mv "$target" "$stage/failed"
        if [[ "$had_previous" == yes ]]; then mv "$stage/previous" "$target"; fi
        echo 'branding_publication_rolled_back=yes'
        result=1
    fi
    echo "branding_backup=$stage"
    exit "$result"
}
trap finish EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
if [[ -e "$target" ]]; then
    [[ -d "$target" && "$(realpath "$target")" == "$target" ]] || exit 1
    had_previous=yes
    mv "$target" "$stage/previous"
fi
armed=yes
mv "$stage/new" "$target"
(cd "$target" && sha256sum -c --status "$stage/expected.sha256")
ca="$private/tls/release-ff1eb07b4c61e61a22331078097b44fed9e85236fad65606065bf15fdf2bf22e/cloudflare-origin-ca-rsa.pem"
for name in index.html privacidade.html termos.html site.css; do
    status=$(curl --silent --show-error --noproxy '*' --max-time 15 --cacert "$ca" --resolve meuprojetoweb.com:443:127.0.0.1 -o "$stage/response" -w '%{http_code}' "https://meuprojetoweb.com/web/central-email/$name")
    [[ "$status" == 200 ]] || exit 1
    cmp --silent "$target/$name" "$stage/response" || exit 1
    printf 'branding_%s_origin_status=200\n' "$name"
done
status=$(curl --silent --show-error --max-time 15 -o /dev/null -w '%{http_code}' https://meuprojetoweb.com/web/login.php)
[[ "$status" == 200 ]] || exit 1
completed=yes
rm -f -- "$stage/response"
echo 'branding_publication_complete=yes'
echo 'core_public_healthy=yes'
echo 'containers_restarted=no'
echo 'database_changed=no'
echo 'mail_center_enabled=no'
