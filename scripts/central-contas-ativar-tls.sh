#!/usr/bin/env bash
set -euo pipefail
umask 077
[[ "${1:-}" == '--activate-origin' ]] || exit 1
private=/opt/private-mail-center
release="$private/tls/release-ff1eb07b4c61e61a22331078097b44fed9e85236fad65606065bf15fdf2bf22e"
override="$private/tls/docker-compose.origin.yml"
expected_image=sha256:4db829095b11f5b72c47da1047e10c493f875657b6829d08f1b35092e75ff683
cd /opt/workspace/docker
exec 9>"$private/tls/activation.lock"
flock -n 9 || { echo 'Outra operação TLS está em andamento.' >&2; exit 1; }
[[ ! -e "$override" && ! -L "$override" ]] || { echo 'Override TLS já existe; revisar estado antes de repetir.' >&2; exit 1; }
[[ "$(docker inspect --format '{{.Image}}' app_php)" == "$expected_image" ]] || { echo 'Imagem mudou desde a inspeção; interrompido.' >&2; exit 1; }
[[ "$(docker inspect --format '{{index .Config.Labels "com.docker.compose.project.config_files"}}' app_php)" == '/opt/workspace/docker/docker-compose.yml,/opt/workspace/docker/docker-compose.prod.yml' ]] || exit 1
if ss -lntH '( sport = :443 )' | grep -q .; then echo 'Porta 443 ocupada; interrompido.' >&2; exit 1; fi
image_tag=$(docker inspect --format '{{.Config.Image}}' app_php)
[[ "$(docker image inspect --format '{{.Id}}' "$image_tag")" == "$expected_image" ]] || exit 1
for name in origin-cert.pem privkey.pem cloudflare-origin-ca-rsa.pem mail-center-origin-ssl.example.conf mail-center.conf; do
    [[ -f "$release/$name" && ! -L "$release/$name" ]] || exit 1
done
openssl verify -CAfile "$release/cloudflare-origin-ca-rsa.pem" -purpose sslserver -verify_hostname meuprojetoweb.com "$release/origin-cert.pem"
openssl x509 -in "$release/origin-cert.pem" -checkend 2592000 -noout
cert_hash=$(openssl x509 -in "$release/origin-cert.pem" -pubkey -noout | openssl pkey -pubin -outform DER | openssl dgst -sha256)
key_hash=$(openssl pkey -in "$release/privkey.pem" -pubout -outform DER | openssl dgst -sha256)
[[ "$cert_hash" == "$key_hash" ]] || exit 1
backup=$(mktemp -d "$private/tls/activation-$(date -u +%Y%m%dT%H%M%SZ)-XXXXXXXX")
base=(docker compose -p docker -f docker-compose.yml -f docker-compose.prod.yml)
"${base[@]}" config --format json >"$backup/before.json"
docker inspect app_php >"$backup/before-container.json"
sha256sum docker-compose.yml docker-compose.prod.yml >"$backup/compose.sha256"
docker image tag "$expected_image" "mail-origin-rollback:$(basename "$backup" | tr '[:upper:]' '[:lower:]')"
printf 'services:\n  app:\n    image: "%s"\n    pull_policy: never\n' "$expected_image" >"$backup/rollback-image.yml"
before_http=$(curl --silent --show-error --max-time 15 -o /dev/null -w '%{http_code}' http://127.0.0.1/web/admin/login.php)
[[ "$before_http" == 200 ]] || exit 1
before_public=$(curl --silent --show-error --max-time 15 -o /dev/null -w '%{http_code}' https://meuprojetoweb.com/web/admin/login.php)
[[ "$before_public" == 200 ]] || exit 1
docker run --rm --network none \
    --mount "type=bind,source=$release/origin-cert.pem,target=/run/mail-center/tls/fullchain.pem,readonly" \
    --mount "type=bind,source=$release/privkey.pem,target=/run/mail-center/tls/privkey.pem,readonly" \
    --mount "type=bind,source=$release/mail-center.conf,target=/etc/apache2/conf-available/mail-center.conf,readonly" \
    --mount "type=bind,source=$release/mail-center-origin-ssl.example.conf,target=/etc/apache2/sites-enabled/mail-center-origin-ssl.conf,readonly" \
    --entrypoint bash "$expected_image" -ec 'a2enmod ssl >/dev/null; a2enconf mail-center >/dev/null; apache2ctl -t' >"$backup/apache-preflight.log" 2>&1
echo 'production_image_apache_preflight=yes'
cat >"$backup/override.yml" <<YAML
services:
  app:
    ports:
      - "443:443"
    command: ["bash", "-ec", "a2enmod ssl >/dev/null; a2enconf mail-center >/dev/null; exec apache2-foreground"]
    volumes:
      - "$release/origin-cert.pem:/run/mail-center/tls/fullchain.pem:ro"
      - "$release/privkey.pem:/run/mail-center/tls/privkey.pem:ro"
      - "$release/mail-center.conf:/etc/apache2/conf-available/mail-center.conf:ro"
      - "$release/mail-center-origin-ssl.example.conf:/etc/apache2/sites-enabled/mail-center-origin-ssl.conf:ro"
YAML
"${base[@]}" -f "$backup/override.yml" config --quiet >"$backup/compose-preflight.log" 2>&1
sha256sum --check --status "$backup/compose.sha256"
[[ "$(docker inspect --format '{{.Image}}' app_php)" == "$expected_image" ]] || exit 1
armed=no
completed=no
finish(){
    local result=$?
    trap - EXIT INT TERM
    if [[ "$armed" == yes && "$completed" != yes ]]; then
        echo 'origin_activation_failed=yes'
        if docker compose -p docker --project-directory /opt/workspace/docker -f "$backup/before.json" -f "$backup/rollback-image.yml" up -d --no-build --no-deps --force-recreate app >"$backup/rollback.log" 2>&1; then
            rm -f -- "$override"
            echo 'origin_rollback_container_restored=yes'
            for attempt in {1..20}; do
                if [[ "$(curl -s --max-time 3 -o /dev/null -w '%{http_code}' http://127.0.0.1/web/admin/login.php || true)" == 200 ]]; then echo 'origin_rollback_http_healthy=yes'; break; fi
                sleep 1
            done
        else
            echo 'origin_rollback_needs_attention=yes'
        fi
        result=1
    fi
    echo "origin_activation_backup=$backup"
    exit "$result"
}
trap finish EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
install -m 0600 "$backup/override.yml" "$override"
armed=yes
"${base[@]}" -f "$override" up -d --no-build --no-deps --force-recreate app >"$backup/activation.log" 2>&1
healthy=no
for attempt in {1..30}; do
    http_status=$(curl -s --max-time 3 -o /dev/null -w '%{http_code}' http://127.0.0.1/web/admin/login.php || true)
    https_status=$(curl -s --noproxy '*' --max-time 3 --cacert "$release/cloudflare-origin-ca-rsa.pem" --resolve meuprojetoweb.com:443:127.0.0.1 -o /dev/null -w '%{http_code}' https://meuprojetoweb.com/web/admin/login.php || true)
    if [[ "$http_status" == 200 && "$https_status" == 200 ]]; then healthy=yes; break; fi
    sleep 1
done
[[ "$healthy" == yes ]] || exit 1
public_status=$(curl --silent --show-error --max-time 15 -o /dev/null -w '%{http_code}' https://meuprojetoweb.com/web/admin/login.php)
[[ "$public_status" == 200 ]] || exit 1
[[ "$(docker inspect --format '{{.Image}}' app_php)" == "$expected_image" ]] || exit 1
completed=yes
echo 'origin_https_active=yes'
echo 'origin_https_verified=yes'
echo 'origin_http_preserved=yes'
echo 'core_public_healthy=yes'
echo 'application_image_unchanged=yes'
echo 'database_changed=no'
echo 'core_environment_changed=no'
echo 'firewall_changed=no'
echo 'cloudflare_changed=no'
echo 'mail_center_enabled=no'
