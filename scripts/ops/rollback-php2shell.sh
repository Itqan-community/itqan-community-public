#!/usr/bin/env bash
# Roll the Flarum vhost back to the PHP 8.1 FPM socket.
#
# This is the pre-staged emergency path for PHP2Shell. It changes nginx and the
# php CLI alternative only: no database, no application code, no package
# removal. 8.1 must still be installed and running.
#
# Usage:
#   sudo ./rollback-php2shell.sh --host community.itqan.dev
#   sudo ./rollback-php2shell.sh --host staging.community.itqan.dev \
#        --probe-path /api/mailerlite/webhook
#
# Exit: 0 service is back on 8.1, 1 rollback failed (previous config restored),
#       2 usage/precondition problem.
set -euo pipefail

CONF=/etc/nginx/sites-available/flarum.conf
BACKUP=
PHP_BIN=/usr/bin/php8.1
HOST=community.itqan.dev
PROBE_PATH=
QUEUE_UNIT=
APP_DIR=

usage() {
    sed -n '2,17p' "$0" | sed 's/^# \{0,1\}//'
}

while [ $# -gt 0 ]; do
    case $1 in
        --conf) CONF=${2:?--conf needs a path}; shift 2 ;;
        --backup) BACKUP=${2:?--backup needs a path}; shift 2 ;;
        --php-bin) PHP_BIN=${2:?--php-bin needs a path}; shift 2 ;;
        --host) HOST=${2:?--host needs a value}; shift 2 ;;
        --probe-path) PROBE_PATH=${2:?--probe-path needs a value}; shift 2 ;;
        --queue-unit) QUEUE_UNIT=${2:?--queue-unit needs a value}; shift 2 ;;
        --app-dir) APP_DIR=${2:?--app-dir needs a path}; shift 2 ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unknown argument: $1" >&2; usage >&2; exit 2 ;;
    esac
done

[ "$(id -u)" = 0 ] || { echo "must run as root" >&2; exit 2; }
[ -f "$CONF" ] || { echo "missing $CONF" >&2; exit 2; }
[ -x "$PHP_BIN" ] || { echo "missing $PHP_BIN; 8.1 was removed?" >&2; exit 2; }

if [ -z "$BACKUP" ]; then
    BACKUP=$(grep -ls 'php8.1-fpm\.sock' /root/flarum.conf.pre-* 2>/dev/null | tail -1 || true)
fi
if [ -z "$BACKUP" ] || [ ! -f "$BACKUP" ]; then
    echo "No 8.1 nginx backup found. Pass --backup /root/<file>." >&2
    echo "Refusing to guess: a wrong config during an incident is worse than stopping." >&2
    exit 2
fi
if ! grep -q 'php8.1-fpm\.sock' "$BACKUP"; then
    echo "$BACKUP does not point at php8.1-fpm.sock; refusing" >&2
    exit 2
fi

if grep -q 'php8.1-fpm\.sock' "$CONF"; then
    echo "Already on the 8.1 socket; nothing to do."
    exit 0
fi

SAVED="/root/flarum.conf.before-rollback.$(date +%Y%m%d-%H%M%S)"
cp "$CONF" "$SAVED"
restore_on_failure() {
    local rc=$?
    if [ "$rc" != 0 ]; then
        echo "ROLLBACK FAILED; restoring the pre-rollback config from $SAVED" >&2
        cp "$SAVED" "$CONF"
        nginx -t && systemctl reload nginx || true
    fi
    exit "$rc"
}
trap restore_on_failure EXIT

start=$(date +%s)
echo "== nginx -> 8.1 ($BACKUP)"
cp "$BACKUP" "$CONF"
grep -n 'fastcgi_pass' "$CONF" || true
nginx -t
systemctl reload nginx

echo "== php CLI -> 8.1"
update-alternatives --set php "$PHP_BIN"

if [ -n "$APP_DIR" ] && [ -f "$APP_DIR/flarum" ]; then
    (cd "$APP_DIR" && sudo -u www-data php flarum cache:clear) \
        || echo "cache clear failed (non-fatal)"
fi
if [ -n "$QUEUE_UNIT" ]; then
    systemctl restart "$QUEUE_UNIT" || echo "could not restart $QUEUE_UNIT (non-fatal)"
fi

echo "== verify"
code=$(curl -sk -o /dev/null -w '%{http_code}' --max-time 15 \
    -H "Host: $HOST" https://127.0.0.1/ || true)
echo "origin_http=$code"
case "$code" in
    200|301|302|401|503) ;;
    *) echo "Origin did not answer acceptably after rollback" >&2; exit 1 ;;
esac

if [ -n "$PROBE_PATH" ]; then
    # A graceful reload can briefly leave old workers serving; poll instead of
    # declaring failure on the first request.
    headers=$(mktemp)
    body=$(mktemp)
    header=
    probe_ok=0
    for _ in $(seq 1 15); do
        code=$(curl -sk -D "$headers" -o "$body" -w '%{http_code}' --max-time 15 -X POST \
            -H "Host: $HOST" -H 'Content-Type: application/json' -d '{}' \
            "https://127.0.0.1$PROBE_PATH" || true)
        header=$(tr -d '\r' < "$headers" | awk -F': ' 'tolower($1)=="x-powered-by"{print $2}')
        body_snip=$(head -c 160 "$body" | tr '\n' ' ')
        echo "probe_http=$code x-powered-by=${header:-<none>} body=$body_snip"
        case "$header" in
            PHP/8.1*) probe_ok=1; break ;;
            "") 
                # Ubuntu ships expose_php=Off for 8.1, so the header may be
                # absent even when PHP served the request. Only use the body
                # as proof when no 8.3 header is claiming otherwise.
                if grep -qiE 'Webhook not configured|method_not_allowed|csrf-token|flarum' "$body"; then
                    echo "PHP served (expose_php is off; verified by the response body)"
                    probe_ok=1
                    break
                fi
                ;;
        esac
        sleep 1
    done
    rm -f "$headers" "$body"
    if [ "$probe_ok" != 1 ]; then
        echo "Probe did not prove PHP was serving after 15s" >&2
        exit 1
    fi
fi

elapsed=$(( $(date +%s) - start ))
echo "OK: back on PHP 8.1 in ${elapsed}s (saved pre-rollback config: $SAVED)"
trap - EXIT
