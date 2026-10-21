#!/usr/bin/env bash
# Fail closed when the PHP runtime serving the forum is still exposed to
# PHP2Shell (CVE-2026-109618 / CVE-2026-109619).
#
# The session.upload_progress and strict-mode settings in .docker/php.ini lower
# exposure but do not fix the bug. Only a patched PHP does, and PHP 8.1 and
# older will never get one. This gate is what stops a deploy from claiming
# success while the runtime underneath is still vulnerable.
#
# Usage:
#   ./php-version-gate.sh                          # check the php on PATH
#   ./php-version-gate.sh --fpm /usr/sbin/php-fpm8.3
#   ./php-version-gate.sh --version 8.3.36         # explicit, used by tests
#   ./php-version-gate.sh --min 8.3.40             # override the advisory file
#   ./php-version-gate.sh --allow-vulnerable       # warn instead of failing
#
# Exit codes: 0 safe (or explicitly overridden), 1 vulnerable, 2 cannot check.
# The override also reads PHP2SHELL_ALLOW_VULNERABLE=1 from the environment so
# an emergency deploy can be unlocked without editing the pipeline.
set -u

here=$(cd "$(dirname "$0")" && pwd)
repo_root=$(cd "$here/../.." && pwd)
fixed_versions_file=${PHP2SHELL_FIXED_VERSIONS:-"$repo_root/security/php2shell-fixed-versions.txt"}

php_bin=php
fpm_bin=
explicit_version=
explicit_min=
allow_vulnerable=${PHP2SHELL_ALLOW_VULNERABLE:-0}
quiet=0

usage() {
    sed -n '2,22p' "$0" | sed 's/^# \{0,1\}//'
}

warn() { printf 'WARNING: %s\n' "$*" >&2; }
fail() { local code=$1; shift; printf 'VULNERABLE: %s\n' "$*" >&2; exit "$code"; }

while [ $# -gt 0 ]; do
    case $1 in
        --php) php_bin=$2; shift 2 ;;
        --fpm) fpm_bin=$2; shift 2 ;;
        --version) explicit_version=$2; shift 2 ;;
        --min) explicit_min=$2; shift 2 ;;
        --allow-vulnerable) allow_vulnerable=1; shift ;;
        --quiet) quiet=1; shift ;;
        -h|--help) usage; exit 0 ;;
        *) printf 'Unknown argument: %s\n' "$1" >&2; usage >&2; exit 2 ;;
    esac
done

if [ -n "$explicit_version" ]; then
    version=$explicit_version
elif [ -n "$fpm_bin" ]; then
    if ! fpm_output=$("$fpm_bin" -v 2>/dev/null); then
        printf 'ERROR: cannot execute %s\n' "$fpm_bin" >&2
        exit 2
    fi
    version=$(printf '%s\n' "$fpm_output" | sed -nE 's/^PHP ([0-9]+\.[0-9]+\.[0-9]+).*/\1/p' | head -1)
else
    version=$("$php_bin" -r 'echo PHP_VERSION;' 2>/dev/null) || {
        printf 'ERROR: cannot execute %s\n' "$php_bin" >&2
        exit 2
    }
fi

if ! printf '%s' "$version" | grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+$'; then
    printf 'ERROR: cannot determine a x.y.z PHP version (got: %s)\n' "${version:-<empty>}" >&2
    exit 2
fi

major=$(printf '%s' "$version" | cut -d. -f1)
minor=$(printf '%s' "$version" | cut -d. -f2)
branch="$major.$minor"

# Anything below 8.2 is out of security support: no fix will be published for
# it, so a high patch level is not evidence of safety.
if [ "$major" -lt 8 ] || { [ "$major" -eq 8 ] && [ "$minor" -lt 2 ]; }; then
    detail="PHP $version is on an end-of-life branch. PHP ships PHP2Shell fixes only for 8.2-8.5, so this runtime stays exploitable."
    if [ "$allow_vulnerable" = 1 ]; then
        warn "$detail Overridden by --allow-vulnerable/PHP2SHELL_ALLOW_VULNERABLE."
        exit 0
    fi
    fail 1 "$detail Upgrade to PHP 8.3. See docs/security/2026-10-21-php2shell.md."
fi

if [ -n "$explicit_min" ]; then
    min_version=$explicit_min
else
    if [ ! -r "$fixed_versions_file" ]; then
        printf 'ERROR: %s is missing; cannot know the fixed version for PHP %s\n' \
            "$fixed_versions_file" "$branch" >&2
        exit 2
    fi
    min_version=$(sed -nE "s/^[[:space:]]*${branch//./\\.}[[:space:]]*=[[:space:]]*([0-9]+\.[0-9]+\.[0-9]+).*/\1/p" \
        "$fixed_versions_file" | head -1)
    if [ -z "$min_version" ]; then
        printf 'ERROR: no fixed version recorded for PHP %s in %s\n' \
            "$branch" "$fixed_versions_file" >&2
        printf 'Add "%s=<fixed version>" from the PHP advisory before deploying.\n' "$branch" >&2
        exit 2
    fi
fi

if ! printf '%s' "$min_version" | grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+$'; then
    printf 'ERROR: invalid minimum version: %s\n' "$min_version" >&2
    exit 2
fi

if ! php -r 'exit(version_compare($argv[1], $argv[2], ">=") ? 0 : 1);' "$version" "$min_version" 2>/dev/null; then
    # `php` may not be on PATH inside a stripped container; fall back to sort -V.
    lowest=$(printf '%s\n%s\n' "$version" "$min_version" | sort -V | head -1)
    if [ "$lowest" != "$min_version" ]; then
        detail="PHP $version predates the $branch fix ($min_version)."
        if [ "$allow_vulnerable" = 1 ]; then
            warn "$detail Overridden by --allow-vulnerable/PHP2SHELL_ALLOW_VULNERABLE."
            exit 0
        fi
        fail 1 "$detail Rebuild/patch the runtime before serving traffic."
    fi
fi

[ "$quiet" = 1 ] || printf 'OK: PHP %s is at or above the %s fix (%s).\n' "$version" "$branch" "$min_version"
exit 0
