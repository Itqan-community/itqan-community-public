#!/usr/bin/env bash
# Run: bash scripts/check-flarum-assets.test.sh
# Exercises check-flarum-assets.sh with a stubbed compile command; no server needed.
set -u

here=$(cd "$(dirname "$0")" && pwd)
fail=0

run() { # name, expected exit, stub compile command
  local name=$1 want=$2 stub=$3
  FLARUM_COMPILE_CMD=$stub bash "$here/check-flarum-assets.sh" >/dev/null 2>&1
  local got=$?
  if [ "$got" = "$want" ]; then echo "PASS $name"; else echo "FAIL $name (exit $got, want $want)"; fail=1; fi
}

run check_compiledBytes_passes 0 'echo "compiled 330025 bytes from 37 sources"'
run check_noisyPhpWarningsThenCompiled_passes 0 'echo "PHP Warning: noise"; echo "compiled 1200 bytes from 3 sources"'
run check_zeroBytes_fails 1 'echo "compiled 0 bytes from 3 sources"'
run check_lessException_fails 1 'echo "Less_Exception_Parser: File forum/gdrive.less not found. in variables.less"; exit 1'
run check_noOutput_fails 1 'true'
run check_crashAfterCompiledLine_fails 1 'echo "compiled 500 bytes from 3 sources"; exit 1'

[ "$fail" = 0 ] && echo "ALL PASS"
exit "$fail"
