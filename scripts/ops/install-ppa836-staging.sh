#!/usr/bin/env bash
# Staging rehearsal of the production path: install the published PPA build
# (php8.3 8.3.36) and switch nginx from the temporary source build to the
# package socket. Keeps PHP 8.1 and the source build intact for rollback.
#
# Runs as root, piped over SSH by the temporary deploy-staging job.
set -euo pipefail

CONF=/etc/nginx/sites-available/flarum.conf
PREV_CONF=/root/flarum.conf.pre-ppa836
PREV_PHP=$(readlink -f /usr/bin/php 2>/dev/null || true)
switched=0
cli_switched=0

rollback() {
    local rc=$?
    if [ "$switched" = 1 ] && [ -f "$PREV_CONF" ]; then
        cp "$PREV_CONF" "$CONF"
        nginx -t && systemctl reload nginx || true
        echo "ROLLED BACK nginx to the previous socket"
    fi
    if [ "$cli_switched" = 1 ] && [ -n "$PREV_PHP" ]; then
        update-alternatives --set php "$PREV_PHP" || true
        echo "ROLLED BACK the php CLI to $PREV_PHP"
    fi
    exit "$rc"
}
trap rollback EXIT

[ "$(id -u)" = 0 ] || { echo "must run as root"; exit 1; }

echo "== Ensuring the PPA is present"
if ! grep -Rqs 'ondrej/php' /etc/apt/sources.list.d/; then
    apt-get update -qq
    apt-get install -y -qq software-properties-common ca-certificates curl
    add-apt-repository -y ppa:ondrej/php >/dev/null
fi

echo "== Refreshing package indexes (retries for Launchpad 503s)"
for i in $(seq 1 10); do
    if apt-get update -qq -o Acquire::Retries=10; then break; fi
    echo "apt update attempt $i failed; retrying"
    sleep 10
done

candidate=$(apt-cache policy php8.3-fpm | awk '/Candidate:/{print $2}')
echo "php8.3-fpm candidate=$candidate"
case "$candidate" in
    8.3.36*) ;;
    *) echo "REFUSING: candidate is not 8.3.36"; exit 1 ;;
esac

echo "== Installing the package"
DEBIAN_FRONTEND=noninteractive apt-get install -y -qq \
    -o Dpkg::Options::=--force-confold \
    php8.3-fpm php8.3-cli php8.3-common php8.3-mysql php8.3-curl \
    php8.3-mbstring php8.3-xml php8.3-zip php8.3-gd php8.3-intl \
    php8.3-bcmath php8.3-opcache
installed=$(php8.3 -r 'echo PHP_VERSION;')
echo "installed=$installed"
[ "$installed" = "8.3.36" ] || { echo "unexpected version: $installed"; exit 1; }

echo "== Writing Flarum limits and PHP2Shell settings"
cat > /etc/php/8.3/fpm/conf.d/99-flarum-php2shell.ini <<'INI'
memory_limit = 256M
upload_max_filesize = 20M
post_max_size = 24M
display_errors = Off
display_startup_errors = Off
log_errors = On
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
session.save_path = "/var/lib/php/sessions"
session.upload_progress.enabled = Off
session.upload_progress.cleanup = On
session.use_strict_mode = 1
INI
mkdir -p /var/lib/php/sessions
chown root:root /var/lib/php/sessions
chmod 1733 /var/lib/php/sessions

for f in /etc/php/8.3/fpm/pool.d/*.conf; do
    grep -q 'php_admin_flag\[session.upload_progress.enabled\]' "$f" \
        || printf '\nphp_admin_flag[session.upload_progress.enabled] = off\n' >> "$f"
    grep -q 'php_admin_flag\[session.use_strict_mode\]' "$f" \
        || printf 'php_admin_flag[session.use_strict_mode] = on\n' >> "$f"
done

php-fpm8.3 -t
systemctl enable --now php8.3-fpm
for _ in $(seq 1 30); do
    [ -S /run/php/php8.3-fpm.sock ] && break
    sleep 1
done
[ -S /run/php/php8.3-fpm.sock ] || { echo "missing /run/php/php8.3-fpm.sock"; exit 1; }

echo "== Switching nginx to the package socket"
cp -n "$CONF" "$PREV_CONF"
sed -E -i 's#unix:(/var)?/run/php/php[0-9.]+-fpm\.sock#unix:/run/php/php8.3-fpm.sock#g' "$CONF"
nginx -t
switched=1
systemctl reload nginx

echo "== Switching CLI to the package build"
update-alternatives --set php /usr/bin/php8.3
cli_switched=1
cd /var/www/flarum
sudo -u www-data php -v | head -1
sudo -u www-data php flarum cache:clear || true

echo "== Verification"
php-fpm8.3 -i | grep -E "^PHP Version|session\.upload_progress\.enabled|session\.use_strict_mode" | tee /tmp/ppa-verify.out
grep -qE "^PHP Version => 8\.3\.36" /tmp/ppa-verify.out
grep -qE "^session\.upload_progress\.enabled => Off" /tmp/ppa-verify.out
grep -qE "^session\.use_strict_mode => On" /tmp/ppa-verify.out
bash scripts/security/php-version-gate.sh --fpm /usr/sbin/php-fpm8.3 --min 8.3.36
curl -sk -i --max-time 20 -X POST -H 'Host: staging.community.itqan.dev' \
    -H 'Content-Type: application/json' -d '{}' \
    https://127.0.0.1/api/mailerlite/webhook | grep -Ei "^HTTP|x-powered-by|Webhook"
curl -sk -o /dev/null -w 'public_http=%{http_code}\n' --max-time 20 \
    -H 'Host: staging.community.itqan.dev' https://127.0.0.1/
rm -f /tmp/ppa-verify.out

switched=0
cli_switched=0
trap - EXIT
echo "OK: staging is serving the PPA php8.3 8.3.36 build; source build and 8.1 kept"
