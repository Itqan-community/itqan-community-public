#!/usr/bin/env bash
# Run: bash scripts/staging-env-check.test.sh
set -u
here=$(cd "$(dirname "$0")" && pwd)
tmp=$(mktemp -d); trap 'rm -rf "$tmp"' EXIT
fail=0
check() { # name expected_exit file [substring...]
  local name=$1 want=$2 f=$3; shift 3
  local out got; out=$(bash "$here/staging-env-check.sh" "$f" 2>&1); got=$?
  local ok=1; [ "$got" = "$want" ] || ok=0
  for s in "$@"; do printf '%s' "$out" | grep -qF -- "$s" || { ok=0; echo "   missing: $s"; }; done
  if [ "$ok" = 1 ]; then echo "PASS $name"; else echo "FAIL $name (exit $got, want $want)"; printf '%s\n' "$out" | sed 's/^/   | /'; fail=1; fi
}

cat > "$tmp/good.php" <<'PHP'
<?php return [
  'queue' => ['driver' => 'database'],
  'mail' => ['driver' => 'log'],
  'filesystems' => ['disks' => ['flarum-assets' => ['driver' => 'local']]],
  'cookie' => ['name' => '__Host-flarum'],
];
PHP
cat > "$tmp/bad.php" <<'PHP'
<?php return [
  'queue' => ['driver' => 'sync'],
  'mail' => ['driver' => 'smtp'],
  'cookie' => ['name' => '__Host-flarum'],
];
PHP
check good 0 "$tmp/good.php" "ENV OK"
check bad  1 "$tmp/bad.php" "queue.driver" "mail.driver" "filesystems.flarum-assets"
exit $fail