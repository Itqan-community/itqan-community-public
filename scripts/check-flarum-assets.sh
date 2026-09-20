#!/usr/bin/env bash
# Fails a deploy when Flarum silently compiled the forum stylesheet to nothing.
#
# A LESS error (e.g. an @import of a file that was never committed) is swallowed
# by Flarum: nothing is logged, the site still answers 200, and rev-manifest.json
# records "forum.css":"empty". The page then renders unstyled.
#
# Usage: check-flarum-assets.sh <site-host>   (run on the server, after cache:clear)
set -uo pipefail

host=${1:?usage: check-flarum-assets.sh <site-host>}
root=${FLARUM_ROOT:-/var/www/flarum}
manifest=$root/public/assets/rev-manifest.json

# cache:clear only wipes assets; the first web request compiles them.
curl -sk -o /dev/null --max-time 60 --resolve "$host:443:127.0.0.1" "https://$host/" || true

if [ -f "$manifest" ] && awk '/"forum\.css":"[0-9a-f]{8}"/ { ok = 1 } END { exit !ok }' "$manifest"; then
  echo "Assets OK: $(cat "$manifest")"
  exit 0
fi

echo "ERROR: forum.css did not compile on $host."
echo "manifest: $(cat "$manifest" 2>/dev/null || echo '<missing>')"
echo "--- compile error:"
sudo -n -u www-data php "$root/scripts/compile-forum-css.php" 2>&1 | tail -5 || true
exit 1
