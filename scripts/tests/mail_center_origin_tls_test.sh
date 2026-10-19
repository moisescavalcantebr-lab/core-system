#!/usr/bin/env bash
set -euo pipefail
# Run only in a disposable container with --network none and private mounts read-only.
cert=/run/mail-center/tls/fullchain.pem
key=/run/mail-center/tls/privkey.pem
ca=/test/_deploy/cloudflare-origin-ca-rsa.pem
openssl verify -CAfile "$ca" -purpose sslserver -verify_hostname meuprojetoweb.com "$cert"
openssl x509 -in "$cert" -noout -checkend 2592000
cert_hash=$(openssl x509 -in "$cert" -pubkey -noout | openssl pkey -pubin -outform DER | openssl dgst -sha256)
key_hash=$(openssl pkey -in "$key" -pubout -outform DER | openssl dgst -sha256)
test "$cert_hash" = "$key_hash"
apache2ctl -t
apache2-foreground >/tmp/apache-test.log 2>&1 &
task_pid=$!
trap 'kill "$task_pid" 2>/dev/null || true' EXIT
task_ready=no
for attempt in {1..15}; do
    if curl --silent --show-error --noproxy '*' --connect-timeout 1 --max-time 3 \
        --cacert "$ca" --resolve meuprojetoweb.com:443:127.0.0.1 \
        https://meuprojetoweb.com/tls-health.txt -o /tmp/tls-health-result 2>/dev/null; then
        task_ready=yes
        break
    fi
    sleep 1
done
if [ "$task_ready" != yes ]; then
    echo 'origin_https_handshake=no' >&2
    tail -n 10 /tmp/apache-test.log >&2
    exit 1
fi
test "$(tr -d '\r\n' </tmp/tls-health-result)" = 'isolated-origin-tls-ok'
echo 'origin_certificate_chain=yes'
echo 'origin_certificate_key_match=yes'
echo 'origin_apache_syntax=yes'
echo 'origin_https_handshake=yes'
echo 'production_changed=no'
