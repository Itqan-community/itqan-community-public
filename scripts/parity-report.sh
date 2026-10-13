#!/usr/bin/env bash
# Reports whether `staging` content matches `main`. Never fetches (no network).
# The verdict is TREE-based (content parity); commit counts are informational.
# PARITY_REPO=dir  PARITY_A=origin/main  PARITY_B=origin/staging  --strict
set -uo pipefail
repo=${PARITY_REPO:-$(cd "$(dirname "$0")/.." && pwd)}
a=${PARITY_A:-origin/main}; b=${PARITY_B:-origin/staging}
strict=0; [ "${1:-}" = "--strict" ] && strict=1

counts=$(git -C "$repo" rev-list --left-right --count "$a...$b" 2>/dev/null) || {
  echo "ERROR: cannot compare $a and $b in $repo"; exit 2; }
behind=$(printf '%s' "$counts" | awk '{print $1}')   # a-only
ahead=$(printf '%s' "$counts" | awk '{print $2}')    # b-only

if git -C "$repo" diff --quiet "$a" "$b" 2>/dev/null; then
  echo "PARITY OK: $b tree == $a tree (commits: $a -$behind / $b +$ahead)"
  exit 0
fi

echo "DRIFT: $b tree differs from $a (commits: $a -$behind / $b +$ahead)"
echo "--- staging-only commits ---"
if git_log_out=$(git -C "$repo" log --oneline "$a..$b" 2>/dev/null); then
  log_total=$(printf '%s\n' "$git_log_out" | wc -l | tr -d ' ')
  printf '%s\n' "$git_log_out" | head -50
  if [ "$log_total" -gt 50 ]; then
    echo "… (truncated; full list: git log $a..$b)"
  fi
else
  echo "(git log failed)"
fi
echo "--- files ---"
if git_diff_out=$(git -C "$repo" diff --stat "$a" "$b" 2>/dev/null); then
  diff_total=$(printf '%s\n' "$git_diff_out" | wc -l | tr -d ' ')
  printf '%s\n' "$git_diff_out" | tail -40
  if [ "$diff_total" -gt 40 ]; then
    echo "… (truncated; full list: git diff --stat $a $b)"
  fi
else
  echo "(git diff failed)"
fi
[ "$strict" = 1 ] && exit 1
exit 0
