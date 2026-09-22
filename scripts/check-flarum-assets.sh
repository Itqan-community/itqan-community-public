#!/usr/bin/env bash
# Fails a deploy when the forum LESS does not compile.
#
# A LESS error (e.g. an @import of a file that was never committed) is swallowed
# by Flarum at runtime: nothing is logged, the site still answers 200, and
# rev-manifest.json records "forum.css":"empty". The page then renders unstyled.
#
# Compiles from the CLI instead of warming via HTTP: a request right after the
# nginx/php-fpm reload races the reload and silently compiles nothing.
#
# Usage: check-flarum-assets.sh   (run on the server, after cache:clear)
set -uo pipefail

root=${FLARUM_ROOT:-/var/www/flarum}
compile=${FLARUM_COMPILE_CMD:-sudo -n -u www-data php $root/scripts/compile-forum-css.php}

out=$(bash -c "$compile" 2>&1)
status=$?

if [ "$status" -eq 0 ] && printf '%s\n' "$out" | awk '/^compiled [1-9][0-9]* bytes/ { ok = 1 } END { exit !ok }'; then
  printf '%s\n' "$out" | awk '/^compiled/'
  echo "Assets OK"
  exit 0
fi

echo "ERROR: forum LESS did not compile."
printf '%s\n' "$out" | awk '!/PHP Warning/' | tail -5
exit 1
