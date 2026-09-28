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

# The extensions under packages/ are path repositories. Registering them the
# normal way needs a full Composer resolve, which also reaches a private VCS
# repository (flarum-lang-arabic) that most contributors have no key for -- so
# without a key, a package added to this repository could never be installed
# and the container would silently run without it.
#
# link-local-extensions.php writes only the symlink and installed.json entry,
# then hands the autoloader back to `composer dump-autoload`, which needs no
# repository access. Every value comes from each package's own composer.json.
# An earlier attempt hand-edited the four Composer-generated files and was
# rejected: a manifest entry without a usable "name" makes
# Extension::nameToId() destructure a missing array element, and the forum then
# died on every request.
log 'Registering local extensions'
php .docker/link-local-extensions.php || echo "  WARNING: local extensions may be incomplete; the forum will still start"

log 'Verifying local extensions are discoverable'
# Only the summary is silenced; FAIL and WARN lines go to stderr and are kept,
# so a broken registration says which extension is at fault. Redirecting stderr
# too would leave the warning unable to name the culprit.
if ! php .docker/verify-local-extensions.php --quiet; then
    echo "  WARNING: not every local extension was found by Flarum (see above)"
fi

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
    #
    # Enabling is an explicit list, unlike registration above which covers
    # everything. Registration is inert; enabling is not. itqan-mailerlite, for
    # one, expects API credentials and a campaign configuration, so enabling it
    # in a developer's fresh container gives them a broken admin page for a
    # service they are not running. Add to this list when a package should come
    # up enabled locally.
    #
    # The loop variable is the full package directory name, so the directory and
    # the extension id are both derived from it once, rather than each being
    # rebuilt — which previously produced packages/itqan-itqan-llms and enabled
    # nothing at all.
    for pkg in itqan-composer-tools itqan-discussions itqan-llms itqan-theme itqan-typography; do
        short="${pkg#itqan-}"
        ext="flarum-$short"
        if [ -d "packages/$pkg" ] && [ -e "vendor/itqan/$ext" ]; then
            su -s /bin/bash www-data -c "php -d error_reporting=0 flarum extension:enable $ext" >/dev/null 2>&1 \
                && echo "  enabled $ext"
        else
            echo "  skipped $ext (not linked into vendor/ — see README)"
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
