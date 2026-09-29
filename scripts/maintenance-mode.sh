#!/usr/bin/env bash
# Turns Flarum maintenance mode on or off on the server, or reports it.
#
# Flarum has no admin toggle: it reads 'offline' from config.php, and while it is
# true the forum, API and admin answer 503 and the queue worker and scheduler pause.
# The deploy workflows regenerate config.php, so `on` also drops a flag file in
# storage/ (untouched by deploys) which deploy-prod.yml uses to put the line back.
#
# Usage: maintenance-mode.sh on|off|status   (FLARUM_ROOT overrides /var/www/flarum)
set -uo pipefail

root=${FLARUM_ROOT:-/var/www/flarum}
config=$root/config.php
flag=$root/storage/maintenance

die() { echo "ERROR: $*" >&2; exit 1; }

[ -f "$config" ] || die "$config not found"

# Rewrites config.php with the 'offline' line removed, and re-added after the
# 'debug' line when $1 is "on". Returns 1 if there is no 'debug' line to anchor on.
apply() {
  local want=$1 tmp backup line indent inserted=0
  tmp=$(mktemp) && backup=$(mktemp) || return 1

  while IFS= read -r line || [ -n "$line" ]; do
    case $line in *"'offline' =>"*) continue ;; esac
    printf '%s\n' "$line" >> "$tmp"
    if [ "$want" = on ] && [ "$inserted" = 0 ]; then
      case $line in
        *"'debug' =>"*)
          indent=${line%%[![:space:]]*}
          printf "%s'offline' => true,\n" "$indent" >> "$tmp"
          inserted=1
          ;;
      esac
    fi
  done < "$config"

  if [ "$want" = on ] && [ "$inserted" = 0 ]; then
    rm -f "$tmp" "$backup"
    echo "ERROR: no 'debug' line in $config to insert after" >&2
    return 1
  fi

  # cat into the existing file (not mv) so owner and mode stay as they were.
  cp -p "$config" "$backup"
  cat "$tmp" > "$config"
  rm -f "$tmp"
  if ! php -l "$config" > /dev/null 2>&1; then
    cat "$backup" > "$config"
    rm -f "$backup"
    echo "ERROR: edited config.php failed php -l; restored the original" >&2
    return 1
  fi
  rm -f "$backup"
}

is_on() { grep -q "'offline' => true," "$config"; }

case ${1:-} in
  on)
    apply on || exit 1
    touch "$flag"
    echo "maintenance: on"
    ;;
  off)
    apply off || exit 1
    rm -f "$flag"
    echo "maintenance: off"
    ;;
  status)
    if is_on; then echo "maintenance: on"; else echo "maintenance: off"; fi
    if is_on && [ ! -e "$flag" ]; then
      echo "Warning: config.php is offline but $flag is missing; the next deploy will turn it off"
    elif ! is_on && [ -e "$flag" ]; then
      echo "Warning: $flag exists but config.php is not offline; the next deploy will turn it on"
    fi
    ;;
  *)
    die "usage: $0 on|off|status"
    ;;
esac
