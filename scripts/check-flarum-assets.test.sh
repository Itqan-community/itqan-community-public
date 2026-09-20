#!/usr/bin/env bash
# Run: bash scripts/check-flarum-assets.test.sh
# Exercises check-flarum-assets.sh against fake Flarum roots; no server needed.
set -u

here=$(cd "$(dirname "$0")" && pwd)
tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT
fail=0

run() { # name, expected exit, manifest content ('' = no manifest file)
  local name=$1 want=$2 manifest=$3 root="$tmp/$1"
  mkdir -p "$root/public/assets" "$root/scripts"
  [ -n "$manifest" ] && printf '%s' "$manifest" > "$root/public/assets/rev-manifest.json"
  FLARUM_ROOT=$root bash "$here/check-flarum-assets.sh" example.test >/dev/null 2>&1
  local got=$?
  if [ "$got" = "$want" ]; then echo "PASS $name"; else echo "FAIL $name (exit $got, want $want)"; fail=1; fi
}

run check_compiledForumCss_passes 0 '{"forum.js":"ab30a8f2","forum.css":"67b59566","forum-ar.css":"438cd31b"}'
run check_forumCssEmpty_fails 1 '{"forum.js":"ab30a8f2","forum.css":"empty","forum-ar.css":"b9a65dff"}'
run check_forumCssMissing_fails 1 '{"forum.js":"ab30a8f2","forum-ar.css":"b9a65dff"}'
run check_manifestEmptyList_fails 1 '[]'
run check_manifestMissing_fails 1 ''

[ "$fail" = 0 ] && echo "ALL PASS"
exit "$fail"
