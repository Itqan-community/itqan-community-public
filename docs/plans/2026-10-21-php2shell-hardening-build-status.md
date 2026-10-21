# PHP2Shell hardening — build status

**Date:** 2026-10-21
**Branch:** `security/php2shell-hardening` (cut from `origin/main` at `de19fad1`)
**Commits:** `c46246b3` (gate, hardening, workflows), `b1736c00` (runbook, status), `ae316fdb` (confirmed fixed versions). Merged to `staging` as `c46a0295` (PR #202). **Production untouched.**
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
| Runtime | PHP 8.3.36 FPM + CLI, source build under `/opt/php-8.3.36` (built in `ubuntu:22.04`), unit `php8.3.36-fpm.service` |
| Why not the PPA | ondrej's jammy PPA still published only 8.3.35, and Launchpad returned 503s during the release rush. The source build is the bridge; replace it with the PPA package when 8.3.36 is published |
| nginx | Both `fastcgi_pass` entries point at `/run/php/php8.3.36-fpm.sock`; previous config backed up at `/root/flarum.conf.pre-php836` |
| 8.1 | Still installed and **hardened** (INI drop-in + pool flags) for rollback; not serving |
| CLI | `update-alternatives` points `php` at `/opt/php-8.3.36/bin/php`; `php8.3.36` / `php-fpm8.3.36` aliases exist for the gate |
| Gate | `OK: PHP 8.3.36 is at or above the 8.3 fix (8.3.36)` on the real staging FPM |
| FPM settings | `session.upload_progress.enabled => Off`, `session.use_strict_mode => On`, `memory_limit 256M`, uploads 20M/24M |
| App proof | Browser fetch to the public MailerLite webhook returned Flarum JSON with `x-powered-by: PHP/8.3.36`, `set-cookie: __Host-flarum_session=…` and `401 {"error":"Webhook not configured"}`; public vhost returns the expected Basic Auth 401 |
| Deploy | PR #202 merged; deploy run `39621445699` passed the gate and reloaded both `php8.1-fpm` and `php8.3.36-fpm` |
| Staging commit | `c46a0295` |

What was not exercised: the authenticated forum UI (staging is behind Basic Auth; the owner can log in with the existing credentials), and a real image upload. Upload progress being disabled does not affect `file_uploads` (verified `On`).

### Pre-production rehearsal (2026-10-21, staging)

- Rollback script tested end to end: nginx + CLI back to 8.1 restored service in
  **1 second**; one in-flight request still hit an 8.3 worker for under a second,
  then the probe proved 8.1 was serving via the Flarum response body (Ubuntu 8.1
  has `expose_php=Off`, so no version header). Restoring 8.3 took **2 seconds**.
- Script: `scripts/ops/rollback-php2shell.sh`.
- PPA check: the 8.3.36 source is published, but the **jammy amd64 binary is
  still Pending**, so the package path could not be rehearsed yet. Staging is
  still on the source build; production should wait for the PPA binary or use
  the source build only with explicit approval.
- Production cutover plan: `docs/security/2026-10-21-php2shell-production-cutover.md`.

### Not verified

- **Production.** Not touched. It still runs PHP 8.1 and the gate will reject its next deploy.
- **Uploads / authenticated UI on staging.** The owner can check with the existing Basic Auth credentials; the runtime and Flarum boot are proven.
- **The source-build replacement path.** When the PPA publishes 8.3.36, switching from `/opt` to the package is documented but not yet executed.
- **Screenshots of the authenticated UI.** The public webhook JSON was captured in a browser; the UI requires credentials we do not hold.
- **The exact fixed versions.** Confirmed after the 2026-10-21 14:07 UTC
  release: 8.2.35, 8.3.36, 8.4.27, 8.5.12. The gate now matches reality.

## Decisions taken

1. **Fail-closed deploy gate.** Merging this blocks the next deploy until PHP is upgraded. The escape hatch is an explicit env var that still logs the warning.
2. **Gate the incoming tree, before `git reset`.** Otherwise a vulnerable runtime could leave new code on disk with an un-migrated database.
3. **Do not enable the multipart block by default.** It breaks every upload on the forum; it stays an emergency snippet with instructions.
4. **Interim settings are described as damage limitation, not a fix,** in every file. PHP 8.1 will never be patched.
5. **Keep PHP 8.1 installed during the 8.3 upgrade** so the nginx socket change can be rolled back.
6. **Local Docker forces `pull: true`.** A cached layer is how a "patched" rebuild silently stays vulnerable.

## Blocked on the owner

1. **Production upgrade.** Production still runs PHP 8.1 and must move to a patched 8.3. The staging runbook and the rollback path are now exercised; production remains gated on your go-ahead.
2. **Replace the staging source build with the PPA package** once ondrej publishes 8.3.36 (the current build is patched, just not package-managed).
3. **Check the authenticated staging UI** with the existing Basic Auth credentials (login, post, upload).
4. Rebuild the local Docker image (`docker compose build --pull`) — the tag should now resolve 8.3.36.
5. Merge the branch to `main` only after production is ready; that merge triggers the production deploy.

## Next step

- When the PPA publishes the **jammy amd64 8.3.36 binary**: switch staging from
  the source build to the package, verify, then follow the production cutover
  plan. Do not use 8.3.35.
- Apply the same `.docker/php.ini` block to `itqan-community-v2` (not deployed
  yet, but it will inherit the same risk).
