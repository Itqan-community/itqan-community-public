#!/usr/bin/env bash
# Staging-only rehearsal of the production rollback. Switches the vhost from
# the PPA 8.3.36 socket back to 8.1, verifies it, then restores 8.3.36 and
# prints how long each direction took. The trap restores 8.3.36 on failure.
set -euo pipefail

here=$(cd "$(dirname "$0")" && pwd)
CONF=/etc/nginx/sites-available/flarum.conf
PPA_CONF=/root/flarum.conf.pre-rollback-test
HOST=staging.community.itqan.dev

[ "$(id -u)" = 0 ] || { echo "must run as root" >&2; exit 2; }
if ! grep -qE 'php8\.3(\.[0-9]+)?-fpm\.sock' "$CONF"; then
    echo "This test expects an 8.3 socket; refusing to run" >&2
    exit 2
fi
PREV_PHP=$(readlink -f /usr/bin/php)
cp "$CONF" "$PPA_CONF"

restore_ppa() {
    local rc=$?
    cp "$PPA_CONF" "$CONF"
    nginx -t && systemctl reload nginx || true
    update-alternatives --set php "$PREV_PHP" || true
    echo "RESTORED the previous 8.3 socket and CLI ($PREV_PHP)" >&2
    exit "$rc"
}
trap restore_ppa EXIT

echo "== Timing the rollback to 8.1"
start=$(date +%s)
bash "$here/rollback-php2shell.sh" --host "$HOST" --probe-path /api/mailerlite/webhook
rollback_seconds=$(( $(date +%s) - start ))
echo "rollback_seconds=$rollback_seconds"

echo "== Restoring the previous 8.3 socket"
start=$(date +%s)
cp "$PPA_CONF" "$CONF"
grep -n 'fastcgi_pass' "$CONF" || true
nginx -t
systemctl reload nginx
update-alternatives --set php "$PREV_PHP"
code=$(curl -sk -o /dev/null -w '%{http_code}' --max-time 15 \
    -H "Host: $HOST" https://127.0.0.1/ || true)
headers=$(mktemp)
body=$(mktemp)
header=
body_snip=
ok=0
for _ in $(seq 1 15); do
    code=$(curl -sk -D "$headers" -o "$body" -w '%{http_code}' --max-time 15 -X POST \
        -H "Host: $HOST" -H 'Content-Type: application/json' -d '{}' \
        "https://127.0.0.1/api/mailerlite/webhook" || true)
    header=$(tr -d '\r' < "$headers" | awk -F': ' 'tolower($1)=="x-powered-by"{print $2}')
    body_snip=$(head -c 120 "$body" | tr '\n' ' ')
    case "$header" in
        PHP/8.3*) ok=1; break ;;
        "")
            if grep -qiE 'Webhook not configured|method_not_allowed|csrf-token|flarum' "$body"; then
                ok=1
                break
            fi
            ;;
    esac
    sleep 1
done
rm -f "$headers" "$body"
echo "origin_http=$code x-powered-by=${header:-<none>} body=$body_snip"
pkg_version=$(php8.3 -r 'echo PHP_VERSION;')
echo "package_php8.3=$pkg_version"
[ "$ok" = 1 ] || { echo "Restore did not prove PHP was serving after 15s" >&2; exit 1; }
[ "$pkg_version" = "8.3.36" ] || { echo "Package runtime is not 8.3.36" >&2; exit 1; }
restore_seconds=$(( $(date +%s) - start ))
echo "restore_seconds=$restore_seconds"

trap - EXIT
echo "OK: rollback rehearsal complete (${rollback_seconds}s down, ${restore_seconds}s to restore)"
