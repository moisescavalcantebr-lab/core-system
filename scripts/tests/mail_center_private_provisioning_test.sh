#!/usr/bin/env bash
set -euo pipefail
# Runs only in the disposable networkless test container.
[[ "${MAIL_PRIVATE_FIXTURE:-}" == isolated && ! -e /opt/private-mail-center ]] || exit 1
source_file=/source/scripts/central-contas-provisionar-privado.sh
docker() {
    case "$*" in
        'exec --user www-data app_php id -u'|'exec --user www-data app_php id -g') printf '33\n' ;;
        'exec -i app_php php') printf '<?php\nreturn ["enabled" => false, "owner_user_id" => 42];\n' ;;
        *) printf 'Unexpected Docker call in fixture.\n' >&2; return 1 ;;
    esac
}
first='{"fixture":"first"}'
second='{"fixture":"second"}'
hash_first=$(printf '%s\n' "$first" | sha256sum);hash_first=${hash_first%% *}
hash_second=$(printf '%s\n' "$second" | sha256sum);hash_second=${hash_second%% *}
if (source "$source_file" "$(printf '0%.0s' {1..64})" <<< "$first") >/dev/null 2>&1; then exit 1; fi
[[ ! -e /opt/private-mail-center/secrets.json ]]
(source "$source_file" "$hash_first" <<< "$first") >/dev/null
[[ "$(stat -c %a /opt/private-mail-center/secrets.json)" == 600 ]]
[[ "$(stat -c %u /opt/private-mail-center/secrets.json)" == 33 ]]
[[ "$(stat -c %a /opt/private-mail-center/attachments)" == 700 ]]
if (source "$source_file" "$hash_second" <<< "$second") >/dev/null 2>&1; then exit 1; fi
after=$(sha256sum /opt/private-mail-center/secrets.json);[[ "${after%% *}" == "$hash_first" ]]
(source "$source_file" "$hash_first" <<< "$first") >/dev/null
grep -Fq '"enabled" => false' /opt/private-mail-center/core-config.php
[[ -z "$(find /opt/private-mail-center -name '.incoming-*' -print -quit)" ]]
printf 'PASS: private provisioning rejects mismatched hashes/overwrites; permissions, disabled owner fragment and repeat execution verified in disposable fixture.\n'
