#!/usr/bin/env bash
# Run: bash scripts/security/php-version-gate.test.sh
# Exercises php-version-gate.sh with explicit version strings and a stubbed
# FPM binary; no PHP runtime, no server and no production access needed.
set -u

here=$(cd "$(dirname "$0")" && pwd)
gate="$here/php-version-gate.sh"
fail=0
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

run() { # name, expected exit, extra args...
    local name=$1 want=$2
    shift 2
    bash "$gate" "$@" >"$work/out" 2>"$work/err"
    local got=$?
    if [ "$got" = "$want" ]; then
        echo "PASS $name"
    else
        echo "FAIL $name (exit $got, want $want)"
        sed 's/^/    /' "$work/out" "$work/err"
        fail=1
    fi
}

expect_err() { # name, pattern
    if grep -q "$2" "$work/err"; then
        echo "PASS $1"
    else
        echo "FAIL $1 (stderr did not match: $2)"
        sed 's/^/    /' "$work/err"
        fail=1
    fi
}

run "php_7_4_fails" 1 --version 7.4.33
run "php_8_1_eol_fails" 1 --version 8.1.33
expect_err "php_8_1_reports_end_of_life" "end-of-life"

run "php_8_2_before_fix_fails" 1 --version 8.2.34
run "php_8_2_fixed_passes" 0 --version 8.2.35
run "php_8_3_before_fix_fails" 1 --version 8.3.35
run "php_8_3_fixed_passes" 0 --version 8.3.36
run "php_8_4_before_fix_fails" 1 --version 8.4.26
run "php_8_4_fixed_passes" 0 --version 8.4.27
run "php_8_5_before_fix_fails" 1 --version 8.5.11
run "php_8_5_fixed_passes" 0 --version 8.5.12

run "unknown_branch_refuses_to_guess" 2 --version 8.6.0
run "garbage_version_refuses_to_guess" 2 --version not-a-version

PHP2SHELL_FIXED_VERSIONS=/nonexistent bash "$gate" --version 8.3.36 >/dev/null 2>&1
got=$?
if [ "$got" = 2 ]; then echo "PASS missing_advisory_file_escape_hatch_unset"; else
    echo "FAIL missing_advisory_file_escape_hatch_unset (exit $got, want 2)"; fail=1
fi

run "explicit_min_overrides_advisory" 0 --version 8.3.35 --min 8.3.35
run "allow_vulnerable_warns_but_passes" 0 --version 8.1.33 --allow-vulnerable
expect_err "allow_vulnerable_says_overridden" "Overridden"

# The deploy gate reads the version from the FPM binary nginx actually uses.
mkdir -p "$work/bin"
make_fpm() { # path, version
    printf '#!/bin/sh\necho "PHP %s (fpm-fcgi) (built: Oct 21 2026)"\n' "$2" > "$1"
    chmod +x "$1"
}
make_fpm "$work/bin/php-fpm8.1" '8.1.2-1ubuntu2.22'
make_fpm "$work/bin/php-fpm8.3" '8.3.36'

run "fpm_8_1_binary_fails" 1 --fpm "$work/bin/php-fpm8.1"
run "fpm_8_3_fixed_binary_passes" 0 --fpm "$work/bin/php-fpm8.3"

printf '#!/bin/sh\nexit 1\n' > "$work/bin/broken-php-fpm"
chmod +x "$work/bin/broken-php-fpm"
run "unreadable_fpm_is_an_error" 2 --fpm "$work/bin/broken-php-fpm"

[ "$fail" = 0 ] && echo "ALL PASS"
exit "$fail"
