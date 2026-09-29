#!/usr/bin/env bash
# Run: bash scripts/maintenance-mode.test.sh
# Exercises maintenance-mode.sh against a throwaway FLARUM_ROOT; no server needed.
set -u

here=$(cd "$(dirname "$0")" && pwd)
script="$here/maintenance-mode.sh"
fail=0

new_root() {
  root=$(mktemp -d)
  mkdir -p "$root/storage"
  cat > "$root/config.php" <<'PHP'
<?php return array (
  'debug' => false,
  'url' => 'https://community.itqan.dev',
);
PHP
}

run() { FLARUM_ROOT=$root bash "$script" "$@" 2>&1; }
offline_lines() { grep -c "'offline' => true," "$root/config.php"; }
lints() { php -l "$root/config.php" >/dev/null 2>&1; }

check() { # name, condition (0 = pass)
  if [ "$2" = 0 ]; then echo "PASS $1"; else echo "FAIL $1"; fail=1; fi
}

new_root
run on >/dev/null
[ "$(offline_lines)" = 1 ] && [ -e "$root/storage/maintenance" ] && lints; check on_addsFlagAndOneValidLine $?

run on >/dev/null
[ "$(offline_lines)" = 1 ]; check on_twice_keepsOneLine $?

run status | grep -q 'maintenance: on'; check status_whenOn_reportsOn $?

run off >/dev/null
[ "$(offline_lines)" = 0 ] && [ ! -e "$root/storage/maintenance" ] && lints; check off_removesFlagAndLine $?

run off >/dev/null
[ $? = 0 ]; check off_whenAlreadyOff_succeeds $?

run status | grep -q 'maintenance: off'; check status_whenOff_reportsOff $?

new_root
touch "$root/storage/maintenance"
run status | grep -qi 'warning'; check status_flagWithoutConfig_warns $?

new_root
chmod 640 "$root/config.php"
run on >/dev/null
[ "$(stat -f %Lp "$root/config.php" 2>/dev/null || stat -c %a "$root/config.php")" = 640 ]; check on_preservesFileMode $?

new_root
sed -i.bak "/'debug'/d" "$root/config.php" && rm -f "$root/config.php.bak"
before=$(cat "$root/config.php")
run on >/dev/null
rc=$?
[ "$rc" != 0 ] && [ "$(cat "$root/config.php")" = "$before" ] && [ ! -e "$root/storage/maintenance" ]; check on_missingDebugAnchor_failsAndLeavesConfig $?

new_root
stub=$(mktemp -d); printf '#!/bin/sh\nexit 255\n' > "$stub/php"; chmod +x "$stub/php"
before=$(cat "$root/config.php")
PATH="$stub:$PATH" run on >/dev/null
rc=$?
[ "$rc" != 0 ] && [ "$(cat "$root/config.php")" = "$before" ] && [ ! -e "$root/storage/maintenance" ]; check on_lintFails_restoresConfigAndFlag $?

new_root
rm "$root/config.php"
run on >/dev/null
[ $? != 0 ]; check on_missingConfig_fails $?

new_root
run bogus >/dev/null
[ $? != 0 ]; check unknownState_fails $?

[ "$fail" = 0 ] && echo "ALL PASS"
exit "$fail"
