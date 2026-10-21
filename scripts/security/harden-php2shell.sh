#!/usr/bin/env bash
# Interim PHP2Shell hardening for servers that run PHP-FPM under nginx.
#
# Writes the two INI directives PHP recommends as damage limitation
# (session.upload_progress.enabled=0, session.use_strict_mode=1) into a drop-in
# for every installed PHP branch, and pins the same values in every FPM pool
# with php_admin_flag so a pool-level override cannot undo them.
#
# This does NOT fix CVE-2026-109618 / CVE-2026-109619. Only a patched PHP does,
# and PHP 8.1 and older will never get an official patch. The script says so,
# loudly, on every run.
#
# Usage:
#   ./scripts/security/harden-php2shell.sh                 # report only
#   sudo ./scripts/security/harden-php2shell.sh --apply    # write + reload
#   sudo ./scripts/security/harden-php2shell.sh --apply --root /tmp/fakeroot
#
# Exit codes in check mode: 0 protected, 1 not protected (or EOL runtime),
# 2 cannot inspect. --apply exits 0 once the files are written and FPM accepts
# its configuration, even on an EOL branch (the version gate still fails).
set -u

root=/
apply=0
quiet=0

usage() {
    sed -n '2,20p' "$0" | sed 's/^# \{0,1\}//'
}

while [ $# -gt 0 ]; do
    case $1 in
        --root) root=${2:?--root needs a path}; shift 2 ;;
        --apply) apply=1; shift ;;
        --quiet) quiet=1; shift ;;
        -h|--help) usage; exit 0 ;;
        *) printf 'Unknown argument: %s\n' "$1" >&2; usage >&2; exit 2 ;;
    esac
done

root=${root%/}
[ -z "$root" ] && root=

say() { [ "$quiet" = 1 ] || printf '%s\n' "$*"; }
err() { printf '%s\n' "$*" >&2; }

php_root="$root/etc/php"
if [ ! -d "$php_root" ]; then
    err "ERROR: $php_root not found; is PHP installed here?"
    exit 2
fi

if [ "$apply" = 1 ] && [ -z "$root" ] && [ "$(id -u)" != 0 ]; then
    err 'ERROR: --apply needs root. Re-run with sudo, or use --root for a dry run.'
    exit 2
fi

dropin_body='; PHP2Shell interim mitigation (CVE-2026-109618 / CVE-2026-109619).
; Damage limitation only: the fix is a patched PHP. Remove this file once the
; runtime is on a fixed release. See docs/security/2026-10-21-php2shell.md.
session.upload_progress.enabled = Off
session.upload_progress.cleanup = On
session.use_strict_mode = 1
'

# Last occurrence wins in PHP; conf.d files are read after php.ini.
read_ini_value() { # dir, directive
    local dir=$1 name=$2 value
    value=$( { grep -hsE "^[[:space:]]*${name}[[:space:]]*=" "$dir/php.ini" 2>/dev/null
               find "$dir/conf.d" -maxdepth 1 -name '*.ini' -type f 2>/dev/null | sort | while read -r f; do
                   grep -hsE "^[[:space:]]*${name}[[:space:]]*=" "$f" 2>/dev/null
               done
             } | tail -1 | sed -E 's/^[^=]*=[[:space:]]*//; s/[[:space:]]*;.*$//; s/[[:space:]]*$//')
    printf '%s' "$value"
}

is_off() { case $(printf '%s' "$1" | tr '[:upper:]' '[:lower:]') in off|0|false|no) return 0 ;; *) return 1 ;; esac; }
is_on()  { case $(printf '%s' "$1" | tr '[:upper:]' '[:lower:]') in on|1|true|yes)   return 0 ;; *) return 1 ;; esac; }

pool_flag_present() { # fpm_dir, flag
    grep -qsE "^[[:space:]]*php_admin_flag\\[$2\\][[:space:]]*=" "$1"/pool.d/*.conf 2>/dev/null
}
write_dropin() { # dir
    mkdir -p "$1/conf.d"
    printf '%s' "$dropin_body" > "$1/conf.d/99-php2shell.ini"
    say "    wrote $1/conf.d/99-php2shell.ini"
}

write_pool_flags() { # fpm_dir
    local f added=0
    for f in "$1"/pool.d/*.conf; do
        [ -e "$f" ] || continue
        if ! pool_flag_present "$1" 'session\.upload_progress\.enabled'; then
            printf '\n; PHP2Shell interim mitigation, added by scripts/security/harden-php2shell.sh\nphp_admin_flag[session.upload_progress.enabled] = off\n' >> "$f"
            added=1
        fi
        if ! pool_flag_present "$1" 'session\.use_strict_mode'; then
            printf 'php_admin_flag[session.use_strict_mode] = on\n' >> "$f"
            added=1
        fi
        say "    pool flags ensured in $f"
    done
    if [ "$added" = 0 ]; then
        say "    pool flags already present in $1/pool.d"
    fi
}

problems=0
eol=0
found=0

for version_dir in "$php_root"/*; do
    [ -d "$version_dir" ] || continue
    version=$(basename "$version_dir")
    printf '%s' "$version" | grep -Eq '^[0-9]+\.[0-9]+$' || continue
    [ -d "$version_dir/fpm" ] || continue
    found=1

    case $version in
        8.2|8.3|8.4|8.5) branch_supported=1 ;;
        *) branch_supported=0 ;;
    esac

    say ""
    say "PHP $version (FPM)"
    if [ "$branch_supported" = 0 ]; then
        say "  runtime: END-OF-LIFE branch — no official PHP2Shell fix. Upgrade to PHP 8.3."
        problems=1
        eol=1
    else
        say "  runtime: supported branch — patch as soon as the fixed build is available."
    fi

    for sapi in fpm apache2; do
        dir="$version_dir/$sapi"
        [ -d "$dir" ] || continue

        if [ "$apply" = 1 ]; then
            case $sapi in
                fpm) write_dropin "$dir"; write_pool_flags "$dir" ;;
                *)   write_dropin "$dir" ;;
            esac
        fi

        up=$(read_ini_value "$dir" 'session\.upload_progress\.enabled')
        strict=$(read_ini_value "$dir" 'session\.use_strict_mode')

        if is_off "$up"; then say "  $sapi session.upload_progress.enabled = Off"; else
            say "  $sapi session.upload_progress.enabled = ${up:-<unset>}  <-- should be Off"; problems=1
        fi
        if is_on "$strict"; then say "  $sapi session.use_strict_mode = On"; else
            say "  $sapi session.use_strict_mode = ${strict:-<unset>}  <-- should be On"; problems=1
        fi

        if [ "$sapi" = fpm ]; then
            if pool_flag_present "$dir" 'session\.upload_progress\.enabled'; then
                say "  fpm pool php_admin_flag[upload_progress] = off"
            else
                say "  fpm pool php_admin_flag[upload_progress] missing"; problems=1
            fi
            if pool_flag_present "$dir" 'session\.use_strict_mode'; then
                say "  fpm pool php_admin_flag[use_strict_mode] = on"
            else
                say "  fpm pool php_admin_flag[use_strict_mode] missing"; problems=1
            fi
        fi
    done

    if [ "$apply" = 1 ] && [ -z "$root" ]; then
        if command -v "php-fpm$version" >/dev/null 2>&1; then
            if "php-fpm$version" -t >/dev/null 2>&1; then
                say "  php-fpm$version -t: OK"
            else
                err "  php-fpm$version -t FAILED — configuration not reloaded"
                problems=1
                continue
            fi
        fi
        if systemctl is-active --quiet "php$version-fpm"; then
            if systemctl reload "php$version-fpm"; then
                say "  reloaded php$version-fpm"
            else
                err "  could not reload php$version-fpm"
                problems=1
            fi
        fi
    fi
done

[ "$found" = 1 ] || { err "ERROR: no /etc/php/<version>/fpm directories found"; exit 2; }

say ""
if [ "$apply" = 0 ] && [ "$problems" != 0 ]; then
    say "NOT PROTECTED: run with --apply (as root) and then re-check."
    exit 1
fi

if [ "$eol" = 1 ]; then
    say "Reminder: the INI settings above reduce exposure only. This server is on an"
    say "end-of-life PHP branch, so the real fix is upgrading to patched PHP 8.3."
fi

exit 0
