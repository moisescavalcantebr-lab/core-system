#!/usr/bin/env bash
set -euo pipefail
# Disposable, networkless fixture. Never run on a host with production paths.
[[ "${MAIL_TLS_ACTIVATION_FIXTURE:-}" == isolated ]] || exit 1
mkdir -p /opt/workspace/docker /opt/private-mail-center/tls /tmp/tls-bin
printf 'services: {}\n' >/opt/workspace/docker/docker-compose.yml
cp /opt/workspace/docker/docker-compose.yml /opt/workspace/docker/docker-compose.prod.yml
tar -cf /tmp/tls-bundle.tar -C /private origin-cert.pem -C /private/production/tls privkey.pem -C /test/_deploy cloudflare-origin-ca-rsa.pem -C /test/docker/php mail-center-origin-ssl.example.conf mail-center.conf
hash=$(sha256sum /tmp/tls-bundle.tar);hash=${hash%% *}
bash /test/scripts/central-contas-stage-tls.sh "$hash" </tmp/tls-bundle.tar >/dev/null
cat >/tmp/tls-bin/docker <<'SH'
#!/usr/bin/env bash
set -eu
image=sha256:4db829095b11f5b72c47da1047e10c493f875657b6829d08f1b35092e75ff683
if [[ "$1" == inspect ]]; then
    if [[ "$*" == *'{{.Image}}'* ]]; then echo "$image"
    elif [[ "$*" == *'{{.Config.Image}}'* ]]; then echo fixture-image
    elif [[ "$*" == *com.docker.compose.project.config_files* ]]; then echo /opt/workspace/docker/docker-compose.yml,/opt/workspace/docker/docker-compose.prod.yml
    else echo '{}'; fi
elif [[ "$1 $2" == 'image inspect' ]]; then echo "$image"
elif [[ "$1" == compose ]]; then
    if [[ "$*" == *'config --format json'* ]]; then echo '{"services":{"app":{"image":"fixture-image"}}}'
    elif [[ "$*" == *'up -d'* ]]; then
        if [[ "$*" == *before.json* ]]; then echo rollback >/tmp/tls-fixture-state
        else echo active >/tmp/tls-fixture-state; fi
    fi
fi
SH
cat >/tmp/tls-bin/curl <<'SH'
#!/usr/bin/env bash
if [[ "${TLS_FIXTURE_FAIL:-no}" == yes && "$(cat /tmp/tls-fixture-state 2>/dev/null)" == active ]]; then printf 503; else printf 200; fi
SH
printf '#!/bin/sh\nexit 0\n' >/tmp/tls-bin/ss
printf '#!/bin/sh\nexit 0\n' >/tmp/tls-bin/sleep
chmod +x /tmp/tls-bin/*
export PATH="/tmp/tls-bin:$PATH"
bash /test/scripts/central-contas-ativar-tls.sh --activate-origin >/tmp/tls-success.txt
grep -q '^origin_https_active=yes$' /tmp/tls-success.txt
test "$(cat /tmp/tls-fixture-state)" = active
# A repeat must stop before mutating an existing active override.
if bash /test/scripts/central-contas-ativar-tls.sh --activate-origin >/dev/null 2>&1; then exit 1; fi
rm -f /opt/private-mail-center/tls/docker-compose.origin.yml /tmp/tls-fixture-state
if TLS_FIXTURE_FAIL=yes bash /test/scripts/central-contas-ativar-tls.sh --activate-origin >/tmp/tls-failure.txt; then exit 1; fi
grep -q '^origin_rollback_container_restored=yes$' /tmp/tls-failure.txt
grep -q '^origin_rollback_http_healthy=yes$' /tmp/tls-failure.txt
test "$(cat /tmp/tls-fixture-state)" = rollback
test ! -e /opt/private-mail-center/tls/docker-compose.origin.yml
echo 'isolated_activation_success_repeat_guard_and_health_failure_rollback=yes'
