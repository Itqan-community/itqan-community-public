#!/usr/bin/env bash
# Reports how far `staging` has drifted from `main`. Never fetches (no network).
# PARITY_REPO=dir  PARITY_A=origin/main  PARITY_B=origin/staging  --strict
set -uo pipefail
repo=${PARITY_REPO:-$(cd "$(dirname "$0")/.." && pwd)}
a=${PARITY_A:-origin/main}; b=${PARITY_B:-origin/staging}
strict=0; [ "${1:-}" = "--strict" ] && strict=1

counts=$(git -C "$repo" rev-list --left-right --count "$a...$b" 2>/dev/null) || {
  echo "ERROR: cannot compare $a and $b in $repo"; exit 2; }
behind=$(printf '%s' "$counts" | awk '{print $1}')   # main-only
ahead=$(printf '%s' "$counts" | awk '{print $2}')    # staging-only

if [ "$behind" = 0 ] && [ "$ahead" = 0 ]; then
  echo "PARITY OK: $b == $a"; exit 0
fi
echo "DRIFT: $a <- $behind commit(s) | $b <- $ahead commit(s)"
echo "--- staging-only (candidate delta) ---"
git -C "$repo" log --oneline --no-merges "$a..$b" | head -50
echo "--- files ---"
git -C "$repo" diff --stat "$a" "$b" | tail -40
[ "$strict" = 1 ] && exit 1
exit 0