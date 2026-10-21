#!/usr/bin/env bash
# Run: bash scripts/security/harden-php2shell.test.sh
# Exercises harden-php2shell.sh against a fake /etc/php tree. No root, no
# systemd, no PHP runtime and no production access needed.
set -u

here=$(cd "$(dirname "$0")" && pwd)
script="$here/harden-php2shell.sh"
fail=0
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

make_root() { # root, php version
    local root=$1 ver=$2
    mkdir -p "$root/etc/php/$ver/fpm/conf.d" "$root/etc/php/$ver/fpm/pool.d"
    printf '[global]\n' > "$root/etc/php/$ver/fpm/php.ini"
    printf '[www]\nuser = www-data\n' > "$root/etc/php/$ver/fpm/pool.d/www.conf"
}

run() { # name, expected exit, args...
    local name=$1 want=$2
    shift 2
    bash "$script" "$@" >"$work/out" 2>"$work/err"
    local got=$?
    if [ "$got" = "$want" ]; then
        echo "PASS $name"
    else
        echo "FAIL $name (exit $got, want $want)"
        sed 's/^/    /' "$work/out" "$work/err"
        fail=1
    fi
}

check_contains() { # name, file, pattern
    if grep -q "$3" "$2"; then echo "PASS $1"; else
        echo "FAIL $1 ($2 does not contain: $3)"; fail=1
    fi
}

check_count() { # name, file, pattern, expected
    local got
    got=$(grep -c "$3" "$2")
    if [ "$got" = "$4" ]; then echo "PASS $1"; else
        echo "FAIL $1 (found $got of '$3', want $4)"; fail=1
    fi
}

# --- 8.1: EOL branch, unprotected -> apply -> directives present ------------
root="$work/eol"
make_root "$root" 8.1

run "8_1_check_reports_not_protected" 1 --root "$root"
check_contains "8_1_check_names_eol" "$work/out" "END-OF-LIFE"

run "8_1_apply_succeeds" 0 --root "$root" --apply
check_contains "8_1_dropin_written" "$root/etc/php/8.1/fpm/conf.d/99-php2shell.ini" "session.upload_progress.enabled = Off"
check_contains "8_1_dropin_strict" "$root/etc/php/8.1/fpm/conf.d/99-php2shell.ini" "session.use_strict_mode = 1"
check_contains "8_1_pool_upload_progress" "$root/etc/php/8.1/fpm/pool.d/www.conf" "php_admin_flag\\[session.upload_progress.enabled\\] = off"
check_contains "8_1_pool_strict" "$root/etc/php/8.1/fpm/pool.d/www.conf" "php_admin_flag\\[session.use_strict_mode\\] = on"

# Applying twice must not duplicate pool flags (FPM rejects repeated keys).
run "8_1_second_apply_succeeds" 0 --root "$root" --apply
check_count "8_1_pool_flags_not_duplicated" "$root/etc/php/8.1/fpm/pool.d/www.conf" "php_admin_flag\\[session.upload_progress.enabled\\]" 1

run "8_1_apply_check_still_flags_eol" 1 --root "$root"

# --- 8.3: supported branch, protected -> check passes ----------------------
root="$work/supported"
make_root "$root" 8.3

run "8_3_check_reports_not_protected" 1 --root "$root"
run "8_3_apply_succeeds" 0 --root "$root" --apply
run "8_3_check_passes_after_apply" 0 --root "$root"

# --- no PHP tree is an error, not a silent pass ----------------------------
run "missing_php_tree_is_an_error" 2 --root "$work/empty"

[ "$fail" = 0 ] && echo "ALL PASS"
exit "$fail"
