#!/usr/bin/env bash
# Detects drift between Cloudflare's published IP ranges and infra/cloudflare-ips.txt,
# the snapshot the prod droplet's DigitalOcean firewall (80/443) was built from.
# A range Cloudflare adds but the firewall lacks means some visitors get 521/522.
#
# Exit: 0 in sync | 1 drift (prints what to change) | 2 could not fetch a sane list
#       (never reported as drift, so a Cloudflare outage cannot look like "all removed").
# Usage: check-cloudflare-ips.sh [--update]   (--update rewrites the snapshot from the live list)
set -uo pipefail
export LC_ALL=C

here=$(cd "$(dirname "$0")" && pwd)
snap=${CF_IPS_SNAPSHOT:-$here/../infra/cloudflare-ips.txt}
fetch=${CF_IPS_FETCH_CMD:-'curl -fsS --max-time 30 https://www.cloudflare.com/ips-v4; echo; curl -fsS --max-time 30 https://www.cloudflare.com/ips-v6'}

norm() { tr -d '\r ' | awk 'NF' | sort -u; }

raw=$(bash -c "$fetch" 2>/dev/null) || { echo "ERROR: fetching Cloudflare ranges failed"; exit 2; }
live=$(printf '%s\n' "$raw" | norm)
count=$(printf '%s\n' "$live" | awk 'NF' | wc -l | tr -d ' ')

if printf '%s\n' "$live" | awk '!/^[0-9a-fA-F:.]+\/[0-9]+$/ { bad = 1 } END { exit !bad }'; then
  echo "ERROR: fetched list has non-CIDR lines (error page?); refusing to compare"; exit 2
fi
if [ "$count" -lt 10 ]; then
  echo "ERROR: fetched only $count ranges (expected 20+); refusing to compare"; exit 2
fi

if [ "${1:-}" = "--update" ]; then
  printf '%s\n' "$live" > "$snap"
  echo "snapshot updated ($count ranges) -> $snap"
  exit 0
fi

cur=$(norm < "$snap")
added=$(comm -13 <(printf '%s\n' "$cur") <(printf '%s\n' "$live"))
removed=$(comm -23 <(printf '%s\n' "$cur") <(printf '%s\n' "$live"))

if [ -z "$added$removed" ]; then
  echo "OK: $count Cloudflare ranges match the snapshot"
  exit 0
fi

rules() { # ranges -> "protocol:tcp,ports:80,address:A,address:B protocol:tcp,ports:443,address:A,address:B"
  local addrs
  addrs=$(printf '%s\n' "$1" | awk 'NF { printf "%saddress:%s", (n++ ? "," : ""), $0 }')
  printf 'protocol:tcp,ports:80,%s protocol:tcp,ports:443,%s' "$addrs" "$addrs"
}

echo "DRIFT: Cloudflare's published ranges differ from infra/cloudflare-ips.txt"
[ -n "$added" ]   && printf '\nADDED (the firewall must allow these):\n%s\n' "$added"
[ -n "$removed" ] && printf '\nREMOVED (can be dropped from the firewall):\n%s\n' "$removed"

cat <<EOF

Remediation, run by a person with DigitalOcean access. Add BEFORE removing so there is no gap.
Find the ID with: doctl compute firewall list   (name: flarum-prod-web-firewall)
EOF
[ -n "$added" ]   && printf '  doctl compute firewall add-rules <FIREWALL_ID> --inbound-rules "%s"\n' "$(rules "$added")"
[ -n "$removed" ] && printf '  doctl compute firewall remove-rules <FIREWALL_ID> --inbound-rules "%s"\n' "$(rules "$removed")"
cat <<'EOF'
Then refresh the snapshot and commit it:
  bash scripts/check-cloudflare-ips.sh --update
EOF
exit 1
