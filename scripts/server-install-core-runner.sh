#!/bin/sh
set -u

LOG_FILE="/tmp/install_core_run.log"
STATUS_FILE="/tmp/install_core_run.status"

if [ "${1:-}" = "--check" ]; then
    if [ -f "${LOG_FILE}" ]; then
        cat "${LOG_FILE}"
    else
        echo "[runner-check] log nao encontrado: ${LOG_FILE}"
    fi

    if [ ! -f "${STATUS_FILE}" ]; then
        echo "[runner-check] status nao encontrado: ${STATUS_FILE}"
        exit 1
    fi

    status="$(cat "${STATUS_FILE}")"
    echo "[runner-check] status salvo: ${status}"
    exit 0
fi

{
    echo "[runner] php version"
    php -v

    echo "[runner] installer file"
    ls -la /tmp/install_core.php

    echo "[runner] installer syntax"
    php -l /tmp/install_core.php

    echo "[runner] running installer"
    php -d display_errors=1 -d log_errors=0 /tmp/install_core.php
    status=$?

    echo "[runner] exit status: ${status}"
    echo "${status}" > "${STATUS_FILE}"
} > "${LOG_FILE}" 2>&1

cat "${LOG_FILE}"
exit "${status}"
