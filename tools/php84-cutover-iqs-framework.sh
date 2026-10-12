#!/bin/bash
set -Eeuo pipefail

SITE_CONFIG="/etc/nginx/sites-available/iqs-framework"
CANDIDATE="/var/www/iqs-framework/docs/php84/iqs-framework.nginx-php84.conf"
POOL_CONFIG="/etc/php/8.4/fpm/pool.d/iqs-framework84.conf"
POOL_SOCKET="/run/php/iqs-framework84.sock"
ROLLBACK_DIR="/var/www/_migration_backups/iqs-framework-php84-20261007-2115/nginx"
ROLLBACK_CONFIG="$ROLLBACK_DIR/iqs-framework.phase4-before"
TEST_URL="https://iqs-framework.upnm.edu.my/"
PROBE_TEMPLATE="/var/www/iqs-framework/docs/php84/runtime-version-probe.php"
PUBLIC_ROOT="/var/www/iqs-framework/public"

if [ "$(id -u)" -ne 0 ]; then
    echo "ERROR: Jalankan script ini menggunakan sudo." >&2
    exit 1
fi

for path in "$SITE_CONFIG" "$CANDIDATE" "$POOL_CONFIG" "$PROBE_TEMPLATE"; do
    [ -f "$path" ] || { echo "ERROR: Fail tiada: $path" >&2; exit 1; }
done

[ -S "$POOL_SOCKET" ] || { echo "ERROR: Socket PHP 8.4 tiada: $POOL_SOCKET" >&2; exit 1; }
systemctl is-active --quiet php8.4-fpm || { echo "ERROR: php8.4-fpm tidak aktif." >&2; exit 1; }
grep -q 'unix:/run/php/php8.3-fpm.sock' "$SITE_CONFIG" || {
    echo "ERROR: Konfigurasi semasa bukan baseline PHP 8.3; hentikan untuk semakan manual." >&2
    exit 1
}
if grep -q 'unix:/run/php/iqs-framework84.sock' "$SITE_CONFIG"; then
    echo "ERROR: Konfigurasi semasa sudah merujuk PHP 8.4; hentikan untuk elak backup rollback yang salah." >&2
    exit 1
fi

mkdir -p "$ROLLBACK_DIR"
cp -p "$SITE_CONFIG" "$ROLLBACK_CONFIG"

probe_name="php84-runtime-probe-$(openssl rand -hex 16).php"
probe_path="$PUBLIC_ROOT/$probe_name"
headers="$(mktemp)"
body="$(mktemp)"
probe_body="$(mktemp)"

cleanup() {
    rm -f "$headers" "$body" "$probe_body" "$probe_path"
}
trap cleanup EXIT

rollback() {
    trap - ERR
    echo "ROLLBACK: Memulihkan konfigurasi Nginx PHP 8.3..." >&2
    cp -p "$ROLLBACK_CONFIG" "$SITE_CONFIG"
    nginx -t
    systemctl reload nginx
}

trap 'echo "ERROR: Cutover gagal." >&2; rollback' ERR

install -o root -g root -m 0644 "$CANDIDATE" "$SITE_CONFIG"
nginx -t

active_socket_count="$(nginx -T 2>&1 | awk '/fastcgi_pass unix:\/run\/php\/iqs-framework84\.sock;/{count++} END{print count+0}')"
if [ "$active_socket_count" -lt 2 ]; then
    echo "ERROR: Konfigurasi aktif Nginx tidak mengandungi kedua-dua rujukan socket PHP 8.4." >&2
    false
fi

systemctl reload nginx

install -o root -g www-data -m 0640 "$PROBE_TEMPLATE" "$probe_path"

# Reload Nginx adalah graceful/asynchronous. Worker lama boleh menjawab dengan
# PHP 8.3 untuk beberapa saat, jadi tunggu sehingga worker baharu mengambil alih.
runtime_version="belum dapat dibaca"
runtime_ready=0
for attempt in $(seq 1 30); do
    : > "$probe_body"
    if curl --fail --silent --show-error --insecure --http1.1 \
        --header 'Connection: close' \
        --header 'Cache-Control: no-store' \
        --resolve iqs-framework.upnm.edu.my:443:127.0.0.1 \
        --output "$probe_body" \
        "https://iqs-framework.upnm.edu.my/$probe_name?cutover=$attempt"; then
        runtime_version="$(tr -d '\r\n' < "$probe_body")"
        if [ "$runtime_version" = '8.4.26' ]; then
            runtime_ready=1
            break
        fi
    else
        runtime_version="HTTP/probe gagal pada cubaan $attempt"
    fi
    sleep 1
done

if [ "$runtime_ready" -ne 1 ]; then
    echo "ERROR: Web runtime bukan PHP 8.4.26 selepas 30 saat (terakhir: $runtime_version)." >&2
    false
fi
rm -f "$probe_path"

curl --fail --silent --show-error --insecure --http1.1 \
    --header 'Connection: close' \
    --resolve iqs-framework.upnm.edu.my:443:127.0.0.1 \
    --dump-header "$headers" \
    --output "$body" \
    "$TEST_URL"

grep -Eq '^HTTP/[0-9.]+ 200' "$headers" || { echo "ERROR: HTTP 200 tidak diterima." >&2; false; }
grep -q '<title>' "$body" || { echo "ERROR: Respons HTML login tidak lengkap." >&2; false; }

trap - ERR
echo "SUCCESS: IQS Framework kini menggunakan PHP 8.4.26."
echo "ROLLBACK CONFIG: $ROLLBACK_CONFIG"
