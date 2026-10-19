#!/usr/bin/env bash
set -euo pipefail
umask 077
# Harmless anonymous requests only; never prints cookie values or raw log lines.
private=/opt/private-mail-center
[[ -d "$private" && ! -L "$private" ]] || exit 1
headers=$(mktemp "$private/.headers-XXXXXXXX")
trap 'rm -f -- "$headers"' EXIT
since=$(date -u +%Y-%m-%dT%H:%M:%SZ)
probe="mail_probe_$(openssl rand -hex 12)"
status=$(curl --silent --show-error --max-time 20 --dump-header "$headers" --output /dev/null --write-out '%{http_code}' "https://meuprojetoweb.com/web/login.php?mail_log_probe=${probe}_control")
[[ "$status" == 200 ]] || exit 1
if grep -qi '^set-cookie:' "$headers"; then
    echo 'public_login_sets_cookie=yes'
    if grep -i '^set-cookie:' "$headers" | grep -qi ';[[:space:]]*secure'; then echo 'public_cookie_secure=yes'; else echo 'public_cookie_secure=no'; fi
    if grep -i '^set-cookie:' "$headers" | grep -qi ';[[:space:]]*httponly'; then echo 'public_cookie_httponly=yes'; else echo 'public_cookie_httponly=no'; fi
    if grep -i '^set-cookie:' "$headers" | grep -qi ';[[:space:]]*samesite=lax'; then echo 'public_cookie_samesite_lax=yes'; else echo 'public_cookie_samesite_lax=no'; fi
else
    echo 'public_login_sets_cookie=no'
fi
for callback in oauth_callback.php oauth_zoho_callback.php; do
    curl --silent --show-error --max-time 20 --output /dev/null "https://meuprojetoweb.com/web/admin/central_contas/$callback?mail_log_probe=${probe}_callback"
done
sleep 2
logs=$(docker logs --since "$since" app_php 2>&1)
if grep -Fq "${probe}_control" <<<"$logs"; then echo 'apache_log_probe_control_seen=yes'; else echo 'apache_log_probe_control_seen=no'; fi
if grep -Fq "${probe}_callback" <<<"$logs"; then echo 'apache_callback_query_redacted=no'; else echo 'apache_callback_query_redacted=yes'; fi
for callback in oauth_callback.php oauth_zoho_callback.php; do
    if grep -Fq "GET /web/admin/central_contas/$callback HTTP/" <<<"$logs"; then printf '%s_safe_path_logged=yes\n' "$callback"; else printf '%s_safe_path_logged=no\n' "$callback"; fi
done
echo 'cloudflare_full_strict_reported_by_owner=yes'
echo 'edge_configuration_read_via_api=no'
echo 'cdn_log_redaction_verified=no'
echo 'real_oauth_tokens_used=no'
echo 'core_environment_changed=no'
