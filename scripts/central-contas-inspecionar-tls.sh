#!/usr/bin/env bash
set -euo pipefail
# Read-only: never emit environment values, credentials or configuration contents.
printf 'app_image_id='
docker inspect --format '{{.Image}}' app_php
printf 'compose_project='
docker inspect --format '{{index .Config.Labels "com.docker.compose.project"}}' app_php
printf 'compose_files='
docker inspect --format '{{index .Config.Labels "com.docker.compose.project.config_files"}}' app_php
printf 'compose_workdir='
docker inspect --format '{{index .Config.Labels "com.docker.compose.project.working_dir"}}' app_php
printf 'app_mounts\n'
docker inspect --format '{{range .Mounts}}{{.Type}} {{.Source}} -> {{.Destination}}{{"\n"}}{{end}}' app_php
printf 'app_networks\n'
docker inspect --format '{{range $name, $details := .NetworkSettings.Networks}}{{$name}}{{"\n"}}{{end}}' app_php
if docker exec app_php apache2ctl -M 2>/dev/null | grep -q ssl_module; then echo 'apache_ssl_enabled=yes'; else echo 'apache_ssl_enabled=no'; fi
if docker exec app_php test -f /usr/lib/apache2/modules/mod_ssl.so; then echo 'apache_ssl_available=yes'; else echo 'apache_ssl_available=no'; fi
if docker exec app_php test -f /etc/apache2/conf-enabled/mail-center.conf; then echo 'callback_log_format_installed=yes'; else echo 'callback_log_format_installed=no'; fi
if ss -lntH '( sport = :443 )' | grep -q .; then echo 'host_443_in_use=yes'; else echo 'host_443_in_use=no'; fi
if command -v ufw >/dev/null; then ufw status | head -n 1; fi
printf 'public_http_status='
curl --silent --show-error --max-time 10 --output /dev/null --write-out '%{http_code}\n' http://127.0.0.1/web/admin/login.php
echo 'tls_preflight_readonly=yes'
