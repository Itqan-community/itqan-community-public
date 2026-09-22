#!/usr/bin/env bash
# Run: bash scripts/check-cloudflare-ips.test.sh
# Exercises check-cloudflare-ips.sh with a stubbed fetch command and temp snapshots; no network.
set -u

here=$(cd "$(dirname "$0")" && pwd)
tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT
fail=0

# 12 valid ranges (>= the script's sanity floor of 10).
base="10.0.0.0/20
10.0.16.0/22
10.0.20.0/22
10.0.24.0/22
10.0.28.0/18
10.1.0.0/17
10.2.0.0/15
10.4.0.0/13
10.12.0.0/14
10.16.0.0/13
2400:cb00::/32
2606:4700::/32"

snap() { printf '%s\n' "$1" > "$tmp/snap.txt"; }
run() { # name, expected exit, live list, [expected output substring...]
  local name=$1 want=$2 live=$3; shift 3
  printf '%s\n' "$live" > "$tmp/live.txt"
  local out got
  out=$(CF_IPS_SNAPSHOT="$tmp/snap.txt" CF_IPS_FETCH_CMD="cat $tmp/live.txt" bash "$here/check-cloudflare-ips.sh" 2>&1); got=$?
  local ok=1
  [ "$got" = "$want" ] || ok=0
  for sub in "$@"; do printf '%s' "$out" | grep -qF -- "$sub" || { ok=0; echo "   missing output: $sub"; }; done
  if [ "$ok" = 1 ]; then echo "PASS $name"; else echo "FAIL $name (exit $got, want $want)"; printf '%s\n' "$out" | sed 's/^/   | /'; fail=1; fi
}

snap "$base"
run check_identical_passes 0 "$base" "OK"
run check_orderAndBlankLinesIgnored_passes 0 "$(printf '\n%s\n\n' "$(printf '%s\n' "$base" | sort -r)")" "OK"
run check_rangeAdded_failsWithAddCommand 1 "$base
203.0.113.0/24" "ADDED" "203.0.113.0/24" "add-rules" "ports:80,address:203.0.113.0/24" "ports:443,address:203.0.113.0/24"
run check_rangeRemoved_failsWithRemoveCommand 1 "$(printf '%s\n' "$base" | grep -v '^10.16.0.0/13$')" "REMOVED" "10.16.0.0/13" "remove-rules"
run check_tooFewRanges_isFetchErrorNotDrift 2 "10.0.0.0/20
10.0.16.0/22" "ERROR"
run check_emptyFetch_isFetchError 2 "" "ERROR"
run check_htmlErrorPage_isFetchError 2 "<html><body>503 Service Unavailable</body></html>
$base" "ERROR"

# fetch command that fails outright
CF_IPS_SNAPSHOT="$tmp/snap.txt" CF_IPS_FETCH_CMD="false" bash "$here/check-cloudflare-ips.sh" >/dev/null 2>&1; got=$?
if [ "$got" = 2 ]; then echo "PASS check_fetchCommandFails_isFetchError"; else echo "FAIL check_fetchCommandFails_isFetchError (exit $got)"; fail=1; fi

# --update rewrites the snapshot, after which the check passes
printf '%s\n' "$base
203.0.113.0/24" > "$tmp/live.txt"
CF_IPS_SNAPSHOT="$tmp/snap.txt" CF_IPS_FETCH_CMD="cat $tmp/live.txt" bash "$here/check-cloudflare-ips.sh" --update >/dev/null 2>&1
CF_IPS_SNAPSHOT="$tmp/snap.txt" CF_IPS_FETCH_CMD="cat $tmp/live.txt" bash "$here/check-cloudflare-ips.sh" >/dev/null 2>&1; got=$?
if [ "$got" = 0 ]; then echo "PASS update_thenCheck_passes"; else echo "FAIL update_thenCheck_passes (exit $got)"; fail=1; fi

# --update must refuse to overwrite the snapshot with a bad fetch
snap "$base"
CF_IPS_SNAPSHOT="$tmp/snap.txt" CF_IPS_FETCH_CMD="echo nope" bash "$here/check-cloudflare-ips.sh" --update >/dev/null 2>&1; got=$?
if [ "$got" = 2 ] && [ "$(wc -l < "$tmp/snap.txt" | tr -d ' ')" = 12 ]; then echo "PASS update_badFetch_keepsSnapshot"; else echo "FAIL update_badFetch_keepsSnapshot (exit $got)"; fail=1; fi

[ "$fail" = 0 ] && echo "ALL PASS"
exit "$fail"
