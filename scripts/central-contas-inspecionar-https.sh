#!/usr/bin/env bash
set -euo pipefail
# Read-only topology probe. Never prints headers, cookies, keys or config files.
printf 'containers_and_ports\n'
docker ps --format '{{.Names}} {{.Ports}}'
printf 'app_network_gateway\n'
docker inspect --format '{{range .NetworkSettings.Networks}}{{.Gateway}}{{"\n"}}{{end}}' app_php
for service in nginx caddy cloudflared; do
    if pgrep -x "$service" >/dev/null 2>&1; then printf 'host_%s=yes\n' "$service"; else printf 'host_%s=no\n' "$service"; fi
done
if command -v nginx >/dev/null 2>&1; then
    config=$(nginx -T 2>/dev/null || true)
    if grep -Eq 'listen[^;]*443' <<< "$config"; then printf 'nginx_tls_listener=yes\n'; else printf 'nginx_tls_listener=no\n'; fi
    if grep -Fq 'proxy_set_header X-Forwarded-Proto $scheme' <<< "$config"; then printf 'nginx_overwrites_forwarded_proto=yes\n'; else printf 'nginx_overwrites_forwarded_proto=no\n'; fi
fi
docker exec app_php php -r '$root="/var/www/html";$text=(string)@file_get_contents($root."/.htaccess");echo "htaccess_trusts_forwarded_proto=".(stripos($text,"X-Forwarded-Proto")!==false?"yes":"no").PHP_EOL;echo "htaccess_trusts_cf_visitor=".(stripos($text,"CF-Visitor")!==false?"yes":"no").PHP_EOL;$config=require $root."/env/env.production.php";echo "session_secure_explicit_false=".((($config["app"]["session_secure"]??null)===false)?"yes":"no").PHP_EOL;'
if command -v curl >/dev/null 2>&1; then
    curl --silent --show-error --max-time 15 --output /dev/null --write-out 'public_https_status=%{http_code}\npublic_tls_verification=%{ssl_verify_result}\n' https://meuprojetoweb.com/web/login.php
fi
printf 'https_topology_probe_complete=yes\n'
