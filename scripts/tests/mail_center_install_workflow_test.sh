#!/usr/bin/env bash
set -euo pipefail
[[ "${MAIL_OVERLAY_FIXTURE:-}" == isolated && -d /test/_deploy ]] || exit 1
source_stage=${1:?Public overlay stage required}
[[ "$source_stage" == /test/_deploy/mail-core-overlay-* ]] || exit 1
mkdir -p /tmp/mail-fixture-bin
cat >/tmp/mail-fixture-bin/docker <<'SH'
#!/usr/bin/env bash
set -eu
if [[ "$1" == inspect ]]; then echo sha256:4db829095b11f5b72c47da1047e10c493f875657b6829d08f1b35092e75ff683
elif [[ "$1" == run ]]; then
    if [[ "$*" == *mail-overlay.json* && "$*" == *foreach* ]]; then
        php -r '$m=json_decode(file_get_contents("/opt/private-mail-center/releases/plan-fixture/files/mail-overlay.json"),true);foreach($m["files"]as$f)echo $f["path"],PHP_EOL;'
    elif [[ "$*" == *check_mail_overlay.php* ]]; then
        php /test/app/console/check_mail_overlay.php --root=/opt/private-mail-center/releases/plan-fixture/files --target=/opt/workspace
    elif [[ "$*" == *check_mail_release.php* ]]; then
        php /test/app/console/check_mail_release.php --root=/opt/private-mail-center/releases/plan-fixture/files
    fi
elif [[ "$1" == compose ]]; then
    if [[ "$*" == *'config --format json'* ]]; then echo '{"services":{"app":{"image":"fixture"}}}'
    elif [[ "$*" == *'up -d'* && "$*" == *compose-before.json* ]]; then echo rollback >/tmp/mail-fixture-state
    elif [[ "$*" == *'up -d'* ]]; then echo install >/tmp/mail-fixture-state; fi
elif [[ "$1" == exec ]]; then
    if [[ "$*" == *'id -g'* ]]; then echo 33
    elif [[ "$2" == app_db ]]; then echo synthetic-database-backup
    elif [[ "$*" == *'migrate_mail_center.php --organization --apply'* && "${MAIL_FIXTURE_FAIL:-no}" == yes ]]; then exit 1
    fi
fi
SH
cat >/tmp/mail-fixture-bin/curl <<'SH'
#!/usr/bin/env bash
set -eu
while (($#)); do
    if [[ "$1" == -D ]]; then shift;printf 'Set-Cookie: synthetic=fixture; Secure; HttpOnly; SameSite=Lax\r\n' >"$1";fi
    shift
done
printf 200
SH
chmod +x /tmp/mail-fixture-bin/*
export PATH="/tmp/mail-fixture-bin:$PATH"
plan=/opt/private-mail-center/releases/plan-fixture
for mode in success failure; do
    # These paths exist only inside this disposable container; source mount is read-only.
    rm -rf /opt/private-mail-center /opt/workspace
    mkdir -p "$plan/files" /opt/workspace/env /opt/workspace/docker /opt/private-mail-center/tls
    cp -a "$source_stage/." "$plan/files/"
    printf 'sha256:4db829095b11f5b72c47da1047e10c493f875657b6829d08f1b35092e75ff683\n' >"$plan/image-id"
    php -r '$m=json_decode(file_get_contents($argv[1]."/mail-overlay.json"),true);foreach(array_keys($m["shared_baselines"])as$p){@mkdir(dirname("/opt/workspace/".$p),0755,true);copy($argv[1]."/".$p,"/opt/workspace/".$p);}' "$plan/files"
    printf '<?php return ["app_url"=>"https://example.test"];\n' >/opt/workspace/env/env.production.php
    cp /opt/workspace/env/env.production.php /tmp/mail-env-before
    printf 'services: {}\n' >/opt/workspace/docker/docker-compose.yml
    cp /opt/workspace/docker/docker-compose.yml /opt/workspace/docker/docker-compose.prod.yml
    printf 'services: {}\n' >/opt/private-mail-center/tls/docker-compose.origin.yml
    if [[ "$mode" == success ]]; then
        bash /test/scripts/central-contas-aplicar-release.sh "$plan" --install-disabled >/tmp/mail-install-success.txt
        grep -q '^mail_code_installed=yes$' /tmp/mail-install-success.txt
        test -f /opt/workspace/app/helpers/session.php
        test -f /opt/private-mail-center/core-env-base.php
    else
        if MAIL_FIXTURE_FAIL=yes bash /test/scripts/central-contas-aplicar-release.sh "$plan" --install-disabled >/tmp/mail-install-failure.txt; then exit 1; fi
        grep -q '^mail_code_and_container_rollback=yes$' /tmp/mail-install-failure.txt
        cmp /tmp/mail-env-before /opt/workspace/env/env.production.php
        test ! -e /opt/workspace/app/helpers/session.php
        test ! -e /opt/workspace/docker/docker-compose.mail-center.prod.yml
        test ! -e /opt/private-mail-center/core-env-base.php
        test "$(cat /tmp/mail-fixture-state)" = rollback
    fi
done
echo 'isolated_install_success_and_migration_failure_rollback=PASS'
