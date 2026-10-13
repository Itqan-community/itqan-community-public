#!/usr/bin/env bash
# Run: bash scripts/staging-refresh.test.sh   (all steps stubbed; no network)
set -u
here=$(cd "$(dirname "$0")" && pwd)
tmp=$(mktemp -d); trap 'rm -rf "$tmp"' EXIT
log="$tmp/log"
export STOP_WORKER_CMD="echo stop_worker >> $log"
export ENV_CHECK_CMD="echo env_check >> $log"
export DUMP_CMD="echo dump_prod >> $log"
export BACKUP_CMD="echo backup_staging >> $log"
export IMPORT_CMD="echo import >> $log"
export SCRUB_CMD="echo scrub >> $log"
export RSYNC_CMD="echo assets >> $log"
export MIGRATE_CMD="echo migrate >> $log"
export START_WORKER_CMD="echo start_worker >> $log"

out=$(bash "$here/staging-refresh.sh" 2>&1); got=$?
fail=0
[ "$got" = 0 ] || { echo "FAIL exit $got"; printf '%s\n' "$out" | sed 's/^/   | /'; fail=1; }
printf '%s' "$out" | grep -qF "REFRESH OK" || { echo "FAIL: no REFRESH OK"; fail=1; }

want=$'stop_worker\nenv_check\ndump_prod\nbackup_staging\nimport\nscrub\nassets\nmigrate\nstart_worker'
have=$(cat "$log")
if [ "$have" != "$want" ]; then
  echo "FAIL: step order"; diff <(printf '%s\n' "$want") <(printf '%s\n' "$have") | sed 's/^/   | /'; fail=1
else
  echo "PASS step order"
fi

if awk '/^scrub$/{s=NR} /^start_worker$/{w=NR} END{exit !(s&&w&&s<w)}' "$log"; then
  echo "PASS scrub precedes worker start"
else
  echo "FAIL: worker started before scrub"; fail=1
fi
[ "$fail" = 0 ] && echo "PASS staging-refresh"
exit $fail
