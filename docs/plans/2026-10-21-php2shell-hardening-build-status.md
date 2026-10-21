# PHP2Shell hardening — build status

**Date:** 2026-10-21
**Branch:** `security/php2shell-hardening` (cut from `origin/main` at `de19fad1`)
**Commits:** `c46246b3` (gate, hardening, workflows), `b1736c00` (runbook, status), `ae316fdb` (confirmed fixed versions). Released through `staging` (PRs #202–#205) and merged to `main` as PR #206 (`a81f2bec`). **Production is live on PHP 8.3.36.**
**Topic:** CVE-2026-109618 / CVE-2026-109619 ("PHP2Shell"), CVSS 9.2 RCE

## What shipped

| File | Why |
|---|---|
| `.docker/php.ini` | Disables session upload progress, enables strict session mode. Damage limitation, not a fix. |
| `docker-compose.yml` | `build.pull: true` so a rebuild actually fetches the patched `php:8.3-apache`, instead of reusing a cached 8.3.35 layer. |
| `.docker/Dockerfile` | Comment documenting the floating tag and why it must not be pinned to a patch level. |
| `scripts/security/php-version-gate.sh` | Fails closed on PHP < 8.2 (EOL, no fix) and on 8.2–8.5 below the fixed patch. `--allow-vulnerable` / `PHP2SHELL_ALLOW_VULNERABLE=1` is the loud override. |
| `security/php2shell-fixed-versions.txt` | Single source of truth for the fixed patch per branch. |
| `scripts/security/harden-php2shell.sh` | Read-only report by default. `--apply` writes the INI drop-in into every installed PHP SAPI, pins both values per FPM pool with `php_admin_flag`, validates with `php-fpm -t`, reloads running FPM services. Idempotent. |
| `.github/workflows/deploy-prod.yml`, `deploy-staging.yml` | Gate runs against the **incoming** tree before the working copy is replaced, so a vulnerable runtime fails the deploy without leaving code ahead of the database. Reload loop is now version-agnostic instead of hard-coded `php8.1-fpm`. |
| `.github/workflows/security-php2shell.yml` | Runs both test scripts and `bash -n` on changes. |
| `security/nginx/php2shell-block-multipart.conf` | Emergency-only stopgap. Not included anywhere; it breaks all uploads. |
| `docs/security/2026-10-21-php2shell.md` | Owner runbook: exposure, interim hardening, 8.1 → 8.3 upgrade, emergency isolation, rollback. |
| `scripts/security/*.test.sh` | 20 gate cases and 14 hardening cases, no server needed. |

## Verification — what was actually run

| Check | Result |
|---|---|
| `bash scripts/security/php-version-gate.test.sh` | **ALL PASS** (20 cases: 7.x/8.1 fail, 8.2.34/8.3.35/8.4.26/8.5.11 fail, fixed patches pass, unknown branch exits 2, FPM `-v` parsing, broken binary exits 2, override warns). |
| `bash scripts/security/harden-php2shell.test.sh` | **ALL PASS** (14 cases: report/apply/idempotency, drop-in and pool flags written, 8.1 still flagged EOL, 8.3 passes after apply, missing tree exits 2). |
| Workflow YAML parse + `bash -n` on every `run:` block | Pass (both deploy workflows, 5 scripts). |
| `docker build --pull` | Image built (`php:8.3.35`, built 2026-10-21 00:33 UTC). |
| HTTP probe inside the built image | `PHP 8.3.35 apache2handler`, `session.upload_progress.enabled=''`, `cleanup='1'`, `use_strict_mode='1'`. |
| Gate against the real image | **correctly refused**: `VULNERABLE: PHP 8.3.35 predates the 8.3 fix (8.3.36)`, exit 1. |
| Local Flarum boot (`docker compose -p icp-php2shell-probe`, port 8083) | Installed, migrated, served HTTP 200; browser loaded the SPA (`window.app === true`), title and welcome page rendered, no console errors after pointing `config.php` at 8083. |
| Browser probe page | Rendered the INI values via a real HTTP request (DOM text read; screenshot unavailable — see below). |

Runtime artefacts from the local boot (`public/assets/*`) were reverted; the worktree is clean apart from `docs/`.

## Live staging status (2026-10-21, verified)

Staging is serving **patched PHP 8.3.36** end to end.

| Item | State |
|---|---|
| Runtime | PHP 8.3.36 FPM + CLI from the **ondrej PPA package**, unit `php8.3-fpm.service`; the source build under `/opt/php-8.3.36` is stopped and kept only as an emergency fallback |
| PPA | The jammy amd64 8.3.36 binary published at ~16:50 UTC; installed and verified on staging |
| nginx | Both `fastcgi_pass` entries point at `/run/php/php8.3-fpm.sock`; previous config backed up at `/root/flarum.conf.pre-php836` and `/root/flarum.conf.pre-ppa836` |
| 8.1 | Still installed and **hardened** (INI drop-in + pool flags) for rollback; not serving |
| CLI | `update-alternatives` points `php` at `/usr/bin/php8.3` (package build) |
| Gate | `OK: PHP 8.3.36 is at or above the 8.3 fix (8.3.36)` on the real staging FPM |
| FPM settings | `session.upload_progress.enabled => Off`, `session.use_strict_mode => On`, `memory_limit 256M`, uploads 20M/24M |
| App proof | Browser fetch to the public MailerLite webhook returned Flarum JSON; the PPA build has `expose_php=Off`, so the version is proven by `php-fpm8.3 -i` / `php8.3 -v` (8.3.36), not a header. Public vhost returns the expected Basic Auth 401 |
| Deploy | PR #202 merged; later PR #205 moved staging to the package build; deploy run `39645484180` passed the gate |
| Staging commit | `d34f8bad` (package-managed 8.3.36) |

What was not exercised: the authenticated forum UI (staging is behind Basic Auth; the owner can log in with the existing credentials), and a real image upload. Upload progress being disabled does not affect `file_uploads` (verified `On`).

### Pre-production rehearsal (2026-10-21, staging)

- PPA path **completed**: the jammy amd64 8.3.36 binary published, installed on
  staging, nginx switched to `/run/php/php8.3-fpm.sock`, and the source-build
  FPM stopped. `php8.3 -v` and `php-fpm8.3 -i` both report 8.3.36.
- Rollback script tested on the final PPA runtime: nginx + CLI back to 8.1
  restored service in **under 1 second**; restoring 8.3 took **1 second**.
  Ubuntu's 8.1 has `expose_php=Off`, so verification uses the Flarum response
  body, not the version header.
- Script: `scripts/ops/rollback-php2shell.sh`.
- Production cutover plan: `docs/security/2026-10-21-php2shell-production-cutover.md`.

### Production (2026-10-21, executed)

| Item | State |
|---|---|
| Runtime | **PHP 8.3.36** from the ondrej PPA, `php8.3-fpm`; 8.1 still installed and hardened for rollback |
| nginx | `fastcgi_pass unix:/run/php/php8.3-fpm.sock`; backup at `/root/flarum.conf.pre-php83` |
| Queue | `flarum-queue.service` restarted on 8.3 and active |
| FPM settings | `memory_limit 256M`, uploads 20M/20M, `upload_progress` Off, strict mode On |
| Deploy | PR #206 merged (`a81f2bec`); gate `OK: PHP 8.3.36…`; 13 MB DB backup; origin and public health 200 |
| App proof | Browser loaded the homepage and a discussion (`window.app === true`); public and `/api/discussions` return 200 |
| First attempt | nginx switched, homepage 500 (`memory_limit 128M` exhausted in the cold LESS compile); the trap restored 8.1 automatically. Retry with 256M succeeded |
| Rollback | Pre-staged at `/root/rollback-php2shell.sh`; not needed after the retry |

### Not verified

- **Authenticated production UI and a real upload.** The public pages and API are verified; login/post/upload remain an owner check.
- **Soak.** Keep 8.1 and the nginx backup for 24–48 hours before cleanup.

## Decisions taken

1. **Fail-closed deploy gate.** Merging this blocks the next deploy until PHP is upgraded. The escape hatch is an explicit env var that still logs the warning.
2. **Gate the incoming tree, before `git reset`.** Otherwise a vulnerable runtime could leave new code on disk with an un-migrated database.
3. **Do not enable the multipart block by default.** It breaks every upload on the forum; it stays an emergency snippet with instructions.
4. **Interim settings are described as damage limitation, not a fix,** in every file. PHP 8.1 will never be patched.
5. **Keep PHP 8.1 installed during the 8.3 upgrade** so the nginx socket change can be rolled back.
6. **Local Docker forces `pull: true`.** A cached layer is how a "patched" rebuild silently stays vulnerable.

## Remaining owner checks

1. **Log in and post on production**, and upload an image — the public pages and API are verified, the authenticated flows are not.
2. **Soak for 24–48 hours**, watching 5xx, `storage/logs`, the queue, and Cloudflare analytics.
3. **Local Docker rebuild** (`docker compose build --pull`) so local matches 8.3.36.
4. After the soak: stop/remove the staging source build under `/opt/php-8.3.36`, remove the `/tmp` tarball, and clean up the temporary staging PPA/ops scripts if no longer needed.

## Next step

- Keep 8.1 and `/root/flarum.conf.pre-php83` until the soak ends. The rollback script is `/root/rollback-php2shell.sh`.
- Apply the same `.docker/php.ini` block to `itqan-community-v2` (not deployed yet, but it will inherit the same risk).
