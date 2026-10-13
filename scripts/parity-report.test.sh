#!/usr/bin/env bash
# Run: bash scripts/parity-report.test.sh   (no network; builds a throwaway repo)
set -u
here=$(cd "$(dirname "$0")" && pwd)
tmp=$(mktemp -d); trap 'rm -rf "$tmp"' EXIT
fail=0
run() { # name expected_exit repo [substring...]
  local name=$1 want=$2 repo=$3; shift 3
  local out got; out=$(PARITY_REPO="$repo" PARITY_A=main PARITY_B=staging bash "$here/parity-report.sh" 2>&1); got=$?
  local ok=1; [ "$got" = "$want" ] || ok=0
  for s in "$@"; do printf '%s' "$out" | grep -qF -- "$s" || { ok=0; echo "   missing: $s"; }; done
  if [ "$ok" = 1 ]; then echo "PASS $name"; else echo "FAIL $name (exit $got, want $want)"; printf '%s\n' "$out" | sed 's/^/   | /'; fail=1; fi
}

# Repo A: main and staging identical.
a="$tmp/a"; git init -q -b master "$a"; git -C "$a" config user.email t@t; git -C "$a" config user.name t
echo 1 > "$a/f"; git -C "$a" add f; git -C "$a" commit -qm init
git -C "$a" branch main master
git -C "$a" branch staging master
run identical 0 "$a" "PARITY OK"

# Repo B: staging has one extra commit.
b="$tmp/b"; git init -q -b master "$b"; git -C "$b" config user.email t@t; git -C "$b" config user.name t
echo 1 > "$b/f"; git -C "$b" add f; git -C "$b" commit -qm init
git -C "$b" branch main master
echo 2 > "$b/g"; git -C "$b" add g; git -C "$b" commit -qm extra
git -C "$b" branch staging master
run drift 0 "$b" "DRIFT" "extra" "--- files ---" " g"

# --strict must exit non-zero when drifted.
out=$(PARITY_REPO="$b" PARITY_A=main PARITY_B=staging bash "$here/parity-report.sh" --strict 2>&1); got=$?
if [ "$got" = 1 ]; then echo "PASS strict"; else echo "FAIL strict (exit $got, want 1)"; fail=1; fi

# --strict on an EQUAL pair must exit 0.
out=$(PARITY_REPO="$a" PARITY_A=main PARITY_B=staging bash "$here/parity-report.sh" --strict 2>&1); got=$?
if [ "$got" = 0 ]; then echo "PASS strict_equal"; else echo "FAIL strict_equal (exit $got, want 0)"; fail=1; fi

exit $fail