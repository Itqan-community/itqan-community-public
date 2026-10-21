# PHP2Shell hardening — build status

**Date:** 2026-10-21
**Branch:** `security/php2shell-hardening` (cut from `origin/main` at `de19fad1`)
**Commits:** `c46246b3` (gate, hardening, workflows) and `2e1db990` (runbook, this status) — local only, **not pushed**
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

### Not verified

- **Production and staging.** No SSH, no live action. Their runtime is PHP 8.1 FPM per the deploy workflows; the gate will reject them until upgraded.
- **A live FPM pool.** The hardening script was exercised against a fake `/etc/php` tree, and the gate against stubbed FPM binaries plus the real container version. Neither proves FPM's effective values on the actual droplet; the runbook's probe page is the honest check.
- **Screenshots.** The harness desktop window is not visible, so browser verification is DOM/JS state, not a screenshot.
- **Uploads with upload-progress disabled.** Flarum does not use PHP's upload-progress feature; `file_uploads` is untouched. Not exercised end to end.
- **The exact fixed versions.** The release was still pending at build time. Values in `security/php2shell-fixed-versions.txt` are the expected next security patches and are marked as such.

## Decisions taken

1. **Fail-closed deploy gate.** Merging this blocks the next deploy until PHP is upgraded. The escape hatch is an explicit env var that still logs the warning.
2. **Gate the incoming tree, before `git reset`.** Otherwise a vulnerable runtime could leave new code on disk with an un-migrated database.
3. **Do not enable the multipart block by default.** It breaks every upload on the forum; it stays an emergency snippet with instructions.
4. **Interim settings are described as damage limitation, not a fix,** in every file. PHP 8.1 will never be patched.
5. **Keep PHP 8.1 installed during the 8.3 upgrade** so the nginx socket change can be rolled back.
6. **Local Docker forces `pull: true`.** A cached layer is how a "patched" rebuild silently stays vulnerable.

## Blocked on the owner (live actions)

1. Apply interim hardening on staging, then production:
   `sudo bash scripts/security/harden-php2shell.sh --apply` (via SSH, after review).
2. Confirm Cloudflare WAF is on and the origin still accepts 80/443 only from Cloudflare ranges.
3. Upgrade production and staging to patched PHP 8.3 once the fixed build is published; run the runbook's probe and gate.
4. Rebuild the local Docker image after the release (`docker compose build --pull`).
5. Push the branch / open the PR, then merge. Nothing was pushed.

## Next step

- After the 14:00 UTC release: confirm the fixed versions, update
  `security/php2shell-fixed-versions.txt` if they differ, and re-run both test
  scripts.
- Apply the same `.docker/php.ini` block to `itqan-community-v2` (not deployed
  yet, so not urgent, but it will inherit the same risk when it ships).
