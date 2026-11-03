#!/bin/bash
# Prepares the local environment on first boot and keeps it current after that.
# Everything here is idempotent: `docker compose up` on an existing install
# migrates and moves on rather than reinstalling.
set -euo pipefail

cd /var/www/html

log() { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }

# storage/ is runtime state and is not in the repository; site.php expects it.
log 'Preparing storage'
mkdir -p storage/{cache,formatter,less,locale,sessions,views,tmp} public/assets/{avatars,files}
chown -R www-data:www-data storage public/assets

log 'Waiting for the database'
until mysqladmin ping -h db -u flarum -pflarum --silent >/dev/null 2>&1; do
    sleep 1
done

# Flarum 2.0 distinguishes MySQL from MariaDB. The `mysql` driver emits
# MySQL-only JSON casts that MariaDB rejects, which surfaces as a 500 on the
# first notification sync (replying to a followed tag, for one). The installer
# used to write `mysql`; correct an existing config.php in place, idempotently.
if [ -f config.php ]; then
    driver=$(php -r '$c = require "config.php"; echo $c["database"]["driver"] ?? "";')
    flavor=$(php -r '
        try {
            $p = new PDO("mysql:host=db;port=3306;dbname=flarum", "flarum", "flarum");
            echo stripos($p->query("select version()")->fetchColumn(), "mariadb") !== false ? "mariadb" : "mysql";
        } catch (Throwable $e) { echo "mysql"; }
    ')
    if [ "$driver" = "mysql" ] && [ "$flavor" = "mariadb" ]; then
        log 'Correcting config.php: MariaDB needs the mariadb driver'
        php -r '
            $c = require "config.php";
            $c["database"]["driver"] = "mariadb";
            file_put_contents("config.php", "<?php return ".var_export($c, true).";\n");
        '
        chown www-data:www-data config.php
    fi
fi

# The extensions under packages/ are path repositories that composer.lock does
# not list yet. Linking them needs a full Composer resolve, which also reaches
# a private VCS repository (flarum-lang-arabic) that most contributors have no
# key for — so this is attempted quietly and never allowed to block the forum
# from starting.
#
# Registering them directly in vendor/composer/installed.json instead was
# tried and rejected: Flarum boots far enough to fatal inside
# Extension::nameToId, and a forum that dies on every request is a worse
# outcome than a forum missing its local extensions.
for pkg in itqan-composer-tools itqan-theme itqan-typography; do
    name="itqan/flarum-${pkg#itqan-}"
    if [ -d "packages/$pkg" ] && [ ! -e "vendor/itqan/flarum-${pkg#itqan-}" ]; then
        log "Linking $name into vendor/"
        if composer update "$name" --no-interaction --no-scripts --quiet 2>/dev/null; then
            echo "  linked (composer.lock updated)"
        else
            echo "  skipped: composer.lock does not list $name, and the resolve"
            echo "  needs access to the private flarum-lang-arabic repository."
            echo "  The forum still runs; that extension just is not installed."
        fi
    fi
done

if [ ! -f config.php ]; then
    log 'Installing Flarum (admin / password123)'
    # Not fatal: on failure the web installer at localhost:8080 is still
    # reachable, which is a better outcome than a container that restarts
    # forever with the reason scrolled out of view.
    if ! su -s /bin/bash www-data -c 'php flarum install --file=.docker/install.yaml'; then
        log 'Automatic install failed — finish it at http://localhost:8080'
        exec "$@"
    fi

    # A fresh install enables nothing, which leaves a forum with no tags, no
    # Markdown and no likes — too far from production to develop against.
    # These are the bundled extensions plus the two this repository maintains;
    # anything needing an external service (Pusher, MailerLite, OAuth,
    # analytics) is deliberately left off.
    log 'Enabling extensions'
    for ext in \
        flarum-tags flarum-markdown flarum-bbcode flarum-emoji \
        flarum-likes flarum-mentions flarum-sticky flarum-lock \
        flarum-subscriptions flarum-flags flarum-approval flarum-suspend \
        flarum-statistics flarum-nicknames \
        askvortsov-markdown-tables irmmr-rtl
    do
        if su -s /bin/bash www-data -c "php -d error_reporting=0 flarum extension:enable $ext" >/dev/null 2>&1; then
            echo "  enabled $ext"
        else
            echo "  skipped $ext (not installed)"
        fi
    done

    # These live in packages/ and only reach Flarum once Composer has linked
    # them. `extension:enable` reports success for an ID it has never heard
    # of, so the vendor directory is what gets checked here.
    for pkg in composer-tools theme typography; do
        if [ -e "vendor/itqan/flarum-$pkg" ]; then
            su -s /bin/bash www-data -c "php -d error_reporting=0 flarum extension:enable itqan-$pkg" >/dev/null 2>&1 \
                && echo "  enabled itqan-$pkg"
        else
            echo "  skipped itqan-$pkg (not linked into vendor/ — see README)"
        fi
    done
else
    log 'Existing install found — running migrations'
    su -s /bin/bash www-data -c 'php flarum migrate'
fi

log 'Publishing assets and clearing the cache'
su -s /bin/bash www-data -c 'php flarum assets:publish' || true
su -s /bin/bash www-data -c 'php flarum cache:clear' || true

log 'Ready on http://localhost:8080  (admin / password123)'

exec "$@"
