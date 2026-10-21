# PHP2Shell — production cutover plan

**Status:** staging is on patched PHP 8.3.36 and the rollback was rehearsed.
**Production has not been touched.** This document is the sequence to follow
after explicit owner confirmation.

## What is already proven on staging

- PPA package path: pending. The PPA source for 8.3.36 is published, but the
  **jammy amd64 binary is still Pending**. Staging currently runs the
  source-built 8.3.36 under `/opt/php-8.3.36`.
- Rollback rehearsal: switching nginx + CLI back to 8.1 restored service in
  **1 second** (one in-flight request still hit an 8.3 worker for under a
  second); restoring 8.3 took **2 seconds**. See
  `docs/plans/2026-10-21-php2shell-hardening-build-status.md`.
- The rollback script is `scripts/ops/rollback-php2shell.sh`. It switches the
  vhost and CLI only; it does not touch the database or application code.
- The app is behind Basic Auth on staging. Production is public, so the
  vulnerable window matters more there.

## Order (do not merge first)

1. Pre-flight and pre-stage (no traffic change).
2. Install/verify 8.3.36 and switch nginx.
3. Soak and verify.
4. Merge `staging → main`, which triggers the normal production deploy.
5. Verify production.

Merging first is possible — the gate fails safely before `git reset` — but it
gives you a red deploy and leaves `main` ahead of production. Patch first.

## Phase 0 — pre-flight (read-only)

- [ ] PPA candidate is **8.3.36**: `apt-cache policy php8.3-fpm`. If it still
      says 8.3.35, **stop** and wait. Do not install 8.3.35.
- [ ] Production is still PHP 8.1 and `php8.1-fpm` is active.
- [ ] Save the working nginx config:
      `cp /etc/nginx/sites-available/flarum.conf /root/flarum.conf.pre-php83`
      and confirm it contains `php8.1-fpm.sock`.
- [ ] Copy every non-default production 8.1 setting (pool `pm.*`,
      `memory_limit`, `post_max_size`, `upload_max_filesize`, `max_input_vars`,
      `session.save_path`, timeouts) into the 8.3 plan. Do not assume defaults.
- [ ] Audit CLI consumers: `systemctl list-units | grep -i flarum`,
      `crontab -l` for root and `www-data`, and `grep -R php8.1 /etc/systemd
      /etc/cron* 2>/dev/null`. Note the queue unit, if any.
- [ ] Check disk for the DB dump and package downloads.
- [ ] Confirm Cloudflare is proxied and the origin still only accepts
      Cloudflare ranges. Keep the WAF rules on.
- [ ] Have the rollback script on the box:
      `/root/rollback-php2shell.sh`, executable, syntax-checked.
- [ ] Take a DB backup (the deploy takes one too, but take one before the
      window).
- [ ] Open two SSH sessions inside `tmux`; one runs the change, one watches
      `curl` and logs.

## Phase 1 — production runtime (no app deploy yet)

Do this inside `tmux`, over SSH, as root. If any step fails before the nginx
switch, users are unaffected.

1. Apply the 8.1 interim hardening first:
   `bash scripts/security/harden-php2shell.sh --apply` (or pipe it from the
   repo). This mitigates 8.1 while you work.
2. Install 8.3.36 from the PPA once the candidate is confirmed:
   `php8.3-fpm php8.3-cli php8.3-common php8.3-mysql php8.3-curl
   php8.3-mbstring php8.3-xml php8.3-zip php8.3-gd php8.3-intl
   php8.3-bcmath php8.3-opcache`.
   Verify `php8.3 -r 'echo PHP_VERSION;'` is exactly `8.3.36`.
3. Write `/etc/php/8.3/fpm/conf.d/99-flarum-php2shell.ini` with the production
   limits plus:
   ```
   session.upload_progress.enabled = Off
   session.upload_progress.cleanup = On
   session.use_strict_mode = 1
   ```
   Pin the two session flags in the FPM pool with `php_admin_flag[...]`.
4. `php-fpm8.3 -t`, `systemctl enable --now php8.3-fpm`, wait for
   `/run/php/php8.3-fpm.sock`.
5. **Pre-switch app proof:** hit 8.3 through a temporary random-path nginx
   location (or a direct FastCGI probe) and verify Flarum boots and sessions
   work. Remove the probe. Do not switch the main vhost until this passes.
6. Cutover: back up the config, replace `php8.1-fpm.sock` with
   `php8.3-fpm.sock`, `nginx -t`, `systemctl reload nginx`.
7. Switch CLI: `update-alternatives --set php /usr/bin/php8.3`; verify
   `sudo -u www-data php -v`; `sudo -u www-data php flarum cache:clear`.
8. Restart the queue worker and any cron/CLI consumer that used 8.1.
9. Verify: `php-fpm8.3 -i` shows 8.3.36 and the session flags; the gate passes
   (`php-version-gate.sh --fpm /usr/sbin/php-fpm8.3`); homepage, login, post,
   image upload, admin page; `storage/logs` has no new fatals.
10. Soak for 10–15 minutes while watching 5xx and logs.

## Phase 2 — deploy the repo change

Only after the runtime is stable:

- [ ] Re-check the release is still only the security commits:
      `git rev-list --count origin/main..origin/staging` should be 6. If main
      moved, sync staging or use a PR from `security/php2shell-hardening`.
- [ ] PR **`staging → main`**, merge it.
- [ ] The deploy gate must print `OK: PHP 8.3.36...`; the deploy backs up the
      DB, resets code, writes `config.php`, migrates, clears cache, reloads
      `php8.1-fpm` and `php8.3-fpm`, and health-checks.
- [ ] Verify production again, including an upload and the admin page.
- [ ] Keep 8.1 and `/root/flarum.conf.pre-php83` for 24–48 hours.

## Rollback (target: under 5 minutes)

The pre-staged script:

```bash
sudo bash /root/rollback-php2shell.sh \
  --host community.itqan.dev \
  --probe-path /api/mailerlite/webhook \
  --app-dir /var/www/flarum \
  --queue-unit <flarum-queue-unit-if-any>
```

What it does:

1. Finds a `/root/flarum.conf.pre-*` backup that points at `php8.1-fpm.sock`
   (or takes `--backup`). Refuses to guess if none exists.
2. Saves the current config, restores the 8.1 config, `nginx -t`, reload.
3. Sets the php CLI alternative to `/usr/bin/php8.1`.
4. Optionally clears Flarum cache and restarts the queue unit.
5. Polls through the graceful-reload window until the origin proves PHP is
   serving (version header or Flarum response body; Ubuntu 8.1 has
   `expose_php=Off`, so the body is the reliable marker).
6. Prints elapsed seconds. On failure it restores the pre-rollback config.

Do not remove 8.1 or the backup until production has soaked.

## Abort triggers

Roll back immediately if any of these happen after the switch:

- 5xx rate rises and stays up for 60 seconds.
- `php8.3-fpm` crashes or restarts.
- Login, post, or upload fails in the smoke test.
- Fatals/deprecation storms in `storage/logs`.
- The queue worker fails.
- Any uncertainty — roll back first, investigate after.

## What can exceed the 5-minute envelope

- Host failure or SSH loss (the change runs in `tmux`; use the console).
- DB corruption or a failed migration requiring restore (the DB backup is the
  recovery path; restore is longer than 5 minutes).
- A production-specific extension that fatals on 8.3 (rollback covers it, but
  the app deploy should not proceed until staging mirrors that extension set).
- Removing 8.1 before soaking (do not).

## Explicit non-actions

- No production SSH, package install, nginx change, merge to `main`, or
  production deploy has been performed.
- No 8.3.35 installation: only the patched 8.3.36 is acceptable.
