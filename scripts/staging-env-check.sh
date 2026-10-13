#!/usr/bin/env bash
# Asserts a Flarum config.php is prod-SHAPED (queue=database, mail=log,
# a flarum-assets disk, __Host cookie). Usage: staging-env-check.sh <config.php>
set -uo pipefail
cfg=${1:?usage: staging-env-check.sh <config.php>}
[ -f "$cfg" ] || { echo "ERROR: no such config: $cfg"; exit 2; }

read -r queue mail disk cookie <<EOF
$(php -r '
$c = include $argv[1];
echo ($c["queue"]["driver"] ?? "-"), " ",
     ($c["mail"]["driver"] ?? "-"), " ",
     (isset($c["filesystems"]["disks"]["flarum-assets"]) ? "yes" : "no"), " ",
     ($c["cookie"]["name"] ?? "-");
' "$cfg" 2>/dev/null)
EOF

fail=0
[ "$queue" = database ] || { echo "FAIL queue.driver=$queue (want database)"; fail=1; }
[ "$mail"  = log ]      || { echo "FAIL mail.driver=$mail (want log)"; fail=1; }
[ "$disk"  = yes ]      || { echo "FAIL filesystems.flarum-assets missing"; fail=1; }
[ "$cookie" = '__Host-flarum' ] || { echo "FAIL cookie.name=$cookie"; fail=1; }
[ "$fail" = 0 ] && echo "ENV OK ($cfg)" || exit 1