#!/usr/bin/env bash
set -euo pipefail
umask 077
# Stage only: no active config, firewall, Docker or Cloudflare changes.
expected=${1:?SHA256 required}
[[ "$expected" =~ ^[a-f0-9]{64}$ ]] || exit 1
private=/opt/private-mail-center
[[ -d "$private" && ! -L "$private" && "$(realpath "$private")" == "$private" ]] || exit 1
[[ ! -L "$private/tls" ]] || exit 1
install -d -m 0700 "$private/tls"
stage=$(mktemp -d "$private/tls/.incoming-XXXXXXXX")
trap 'rm -rf -- "$stage"' EXIT
cat >"$stage/bundle.tar"
actual=$(sha256sum "$stage/bundle.tar");actual=${actual%% *}
[[ "$actual" == "$expected" ]] || { echo 'tls_bundle_integrity=no' >&2; exit 1; }
expected_names=$(printf '%s\n' origin-cert.pem privkey.pem cloudflare-origin-ca-rsa.pem mail-center-origin-ssl.example.conf mail-center.conf | sort)
[[ "$(tar -tf "$stage/bundle.tar" | sort)" == "$expected_names" ]] || exit 1
mkdir "$stage/files"
tar --extract --file "$stage/bundle.tar" --directory "$stage/files" --no-same-owner --no-same-permissions
for name in origin-cert.pem privkey.pem cloudflare-origin-ca-rsa.pem mail-center-origin-ssl.example.conf mail-center.conf; do
    [[ -f "$stage/files/$name" && ! -L "$stage/files/$name" ]] || exit 1
done
cert="$stage/files/origin-cert.pem"
ca="$stage/files/cloudflare-origin-ca-rsa.pem"
openssl verify -CAfile "$ca" -purpose sslserver -verify_hostname meuprojetoweb.com "$cert"
openssl x509 -in "$cert" -noout -checkend 2592000
cert_hash=$(openssl x509 -in "$cert" -pubkey -noout | openssl pkey -pubin -outform DER | openssl dgst -sha256)
key_hash=$(openssl pkey -in "$stage/files/privkey.pem" -pubout -outform DER | openssl dgst -sha256)
[[ "$cert_hash" == "$key_hash" ]] || exit 1
fingerprint=$(openssl x509 -in "$cert" -outform DER | sha256sum);fingerprint=${fingerprint%% *}
release="$private/tls/release-$fingerprint"
[[ ! -L "$release" ]] || exit 1
if [[ -e "$release" ]]; then
    [[ -d "$release" ]] || exit 1
    for name in origin-cert.pem privkey.pem cloudflare-origin-ca-rsa.pem mail-center-origin-ssl.example.conf mail-center.conf; do
        cmp --silent "$stage/files/$name" "$release/$name" || { echo 'existing_tls_release_differs=yes' >&2; exit 1; }
    done
else
    chmod 0700 "$stage/files"
    chmod 0600 "$stage/files/"*
    mv "$stage/files" "$release"
fi
echo "tls_release=$release"
echo 'tls_certificate_verified=yes'
echo 'tls_key_match=yes'
echo 'tls_files_private=yes'
echo 'tls_staged_only=yes'
echo 'containers_restarted=no'
echo 'firewall_changed=no'
echo 'cloudflare_changed=no'
