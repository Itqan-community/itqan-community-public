<?php
// Purge/rewrite the private + active fields on the STAGING database after a
// prod-content import. Portable across MySQL (server) and SQLite (tests).
// NEVER run against production. Usage:
//   php staging-scrub.php /var/www/flarum/config.php [--dry-run]
// Env: SCRUB_DSN / SCRUB_USER / SCRUB_PASS (override config), SCRUB_FROM_HOST,
//      SCRUB_TO_HOST, SCRUB_DENY_SETTINGS, SCRUB_DENY_HOSTS,
//      STAGING_ADMIN_USERNAME, STAGING_ADMIN_PASSWORD.

// Phrase fragments (case-insensitive) that mean "the object I asked for isn't there".
// Anything else is a real error and must propagate.
const ITQAN_ABSENT_PHRASES = [
    'no such table',
    'no such column',
    "doesn't exist",
    'unknown column',
    'unknown table',
    'base table or view not found',
];

function itqan_is_absent(PDOException $e): bool
{
    $msg = strtolower($e->getMessage());
    foreach (ITQAN_ABSENT_PHRASES as $p) {
        if (str_contains($msg, $p)) { return true; }
    }
    return false;
}

function itqan_table_exists(PDO $db, string $t): bool
{
    try { $db->query("SELECT 1 FROM `$t` LIMIT 0"); return true; }
    catch (PDOException $e) { if (itqan_is_absent($e)) { return false; } throw $e; }
}

function itqan_col_exists(PDO $db, string $t, string $c): bool
{
    try { $db->query("SELECT `$c` FROM `$t` LIMIT 0"); return true; }
    catch (PDOException $e) { if (itqan_is_absent($e)) { return false; } throw $e; }
}

function itqan_scrub(PDO $db, array $o = []): array
{
    $purge = [
        // auth / impersonation
        'access_tokens', 'email_tokens', 'password_tokens', 'registration_tokens',
        'login_providers',
        // push / devices
        'push_subscriptions', 'firebase_push_subscriptions',
        // queues
        'queue_jobs', 'queue_failed_jobs', 'jobs', 'failed_jobs',
        // tracking / outbound
        'html_headers', 'webhooks',
        // secrets
        'api_keys',
        // PII lists
        'mailerlite_subscribers', 'digest_subscribers', 'newsletter_subscribers',
        'digest_sends', 'sent_newsletters',
        // view data (contains IPs; excluded from the dump anyway)
        'discussion_views',
        // admin audit trail (contains IPs / user agents)
        'admin_audit_logs',
    ];
    $suffix = $o['email_suffix'] ?? '@staging.invalid';
    $report = [];
    $dry = !empty($o['dry_run']);
    $driver = (string) $db->getAttribute(PDO::ATTR_DRIVER_NAME);

    // 1. Kill impersonation + device + queued-work channels first.
    //    On MySQL we use TRUNCATE — instant on InnoDB even for huge tables
    //    (DELETE FROM t can take many minutes on hundreds of thousands of rows
    //    and is not what we want here). SQLite doesn't support TRUNCATE, so we
    //    fall back to DELETE there (tests). In --dry-run we report the row
    //    count we'd purge without touching the table, so the operator can see
    //    the size of each table before running for real.
    foreach ($purge as $t) {
        if (!itqan_table_exists($db, $t)) { $report[$t] = 'absent'; continue; }
        $n = (int) $db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        if ($dry) { $report[$t] = 'would-purge:' . $n; continue; }
        $db->exec($driver === 'mysql' ? "TRUNCATE TABLE `$t`" : "DELETE FROM `$t`");
        $report[$t] = 'purged:' . $n;
    }

    // 2. Fake every email; lock every password behind an unknown random hash
    //    (the column is NOT NULL on MySQL). The admin is restored in step 4.
    if (itqan_table_exists($db, 'users') && itqan_col_exists($db, 'users', 'email')) {
        $hasPw = itqan_col_exists($db, 'users', 'password');
        $lockHash = $hasPw ? password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT) : null;
        $sql = "UPDATE `users` SET email = ?" . ($hasPw ? ", password = ?" : "") . " WHERE id = ?";
        $up = $db->prepare($sql);
        $ids = $db->query("SELECT id FROM `users`")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            $hasPw ? $up->execute(["u{$id}{$suffix}", $lockHash, $id])
                   : $up->execute(["u{$id}{$suffix}", $id]);
        }
        $report['users_email'] = 'faked:' . count($ids);
    }

    // 3. Rewrite prod hostnames in stored content so staging self-hosts.
    $from = $o['from_host'] ?? ''; $to = $o['to_host'] ?? '';
    if ($from !== '' && $to !== '' && $from !== $to) {
        foreach ([['posts', 'content'], ['users', 'avatar_url'], ['fof_upload_files', 'url']] as [$t, $c]) {
            if (itqan_table_exists($db, $t) && itqan_col_exists($db, $t, $c)) {
                $st = $db->prepare("UPDATE `$t` SET `$c` = REPLACE(`$c`, ?, ?)");
                $st->execute([$from, $to]);
                $report["rewrite:$t.$c"] = $st->rowCount();
            }
        }
        if (itqan_table_exists($db, 'settings') && itqan_col_exists($db, 'settings', 'value')) {
            $st = $db->prepare("UPDATE `settings` SET `value` = REPLACE(`value`, ?, ?) WHERE `value` LIKE ?");
            $st->execute([$from, $to, '%' . $from . '%']);
            $report['rewrite:settings.value'] = $st->rowCount();
        }
    }

    // 3b. Neutralise settings that could reach production.
    //     mail_* is ALWAYS removed and the driver forced to 'log' so nothing can
    //     send, even if a default driver is 'smtp'.
    $denyRaw = $o['deny_settings'] ?? '';
    if ($denyRaw === '') {
        $denyRaw = 'fof-analytics,ianm-translate,fof-oauth,fof-upload,'
            . 'itqan-mailerlite,askvortsov-pwa,fof-webhooks,custom_header,custom_footer';
    }
    $denySettings = array_filter(array_map('trim', explode(',', $denyRaw)));
    if (itqan_table_exists($db, 'settings') && itqan_col_exists($db, 'settings', 'key')) {
        foreach (array_merge($denySettings, ['mail_']) as $prefix) {
            $st = $db->prepare("DELETE FROM `settings` WHERE `key` LIKE ?");
            $st->execute([$prefix . '%']);
            if ($st->rowCount()) { $report["settings:$prefix"] = 'deleted:' . $st->rowCount(); }
        }
        if (itqan_col_exists($db, 'settings', 'value')) {
            $db->prepare("DELETE FROM `settings` WHERE `key` = 'mail_driver'")->execute();
            $db->prepare("INSERT INTO `settings` (`key`, `value`) VALUES ('mail_driver', 'log')")->execute();
            $report['settings:mail_driver'] = 'set:log';
        }
    }

    // 4. Restore a staging-only admin login.
    $u = $o['admin_username'] ?? ''; $p = $o['admin_password'] ?? '';
    if ($u !== '' && $p !== '' && itqan_col_exists($db, 'users', 'password')) {
        $st = $db->prepare("UPDATE `users` SET password = ? WHERE username = ?");
        $st->execute([password_hash($p, PASSWORD_BCRYPT), $u]);
        $report['admin'] = $st->rowCount() ? "set:$u" : "MISSING:$u";
    }
    return $report;
}

function itqan_scrub_main(array $argv): int
{
    $cfg = $argv[1] ?? '/var/www/flarum/config.php';
    $dry = in_array('--dry-run', $argv, true);
    $dsn = getenv('SCRUB_DSN') ?: '';
    // Production-host denylist. Only enforced when we resolved a host from a
    // config file (the path the scrub was designed for). SCRUB_DSN bypass is
    // deliberate: it is the testing escape hatch and the operator-controlled
    // override. The default 10.106.0.5 is the production DB host.
    $resolvedHost = null;
    if ($dsn === '') {
        $c = include $cfg;
        $d = $c['database'];
        $resolvedHost = (string) ($d['host'] ?? '');
        $deny = array_filter(array_map('trim', explode(',', getenv('SCRUB_DENY_HOSTS') ?: '10.106.0.5')));
        if ($resolvedHost !== '' && in_array($resolvedHost, $deny, true)) {
            fwrite(STDERR, "REFUSING: host {$resolvedHost} is a production host (set SCRUB_DENY_HOSTS to override)\n");
            return 2;
        }
    }
    if ($dsn !== '') {
        $db = new PDO($dsn, getenv('SCRUB_USER') ?: null, getenv('SCRUB_PASS') ?: null);
    } else {
        $c = include $cfg;
        $d = $c['database'];
        $db = new PDO("mysql:host={$d['host']};dbname={$d['database']};charset=utf8mb4",
            $d['username'], $d['password']);
    }
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $opts = [
        'from_host' => getenv('SCRUB_FROM_HOST') ?: '',
        'to_host' => getenv('SCRUB_TO_HOST') ?: '',
        'deny_settings' => getenv('SCRUB_DENY_SETTINGS') ?: '',
        'admin_username' => getenv('STAGING_ADMIN_USERNAME') ?: '',
        'admin_password' => getenv('STAGING_ADMIN_PASSWORD') ?: '',
    ];
    $opts['dry_run'] = $dry;
    // Only wrap in a transaction for the dry-run rollback. TRUNCATE is DDL on
    // MySQL and would implicitly commit, so we cannot keep an enclosing
    // transaction on the live path — it would also be useless since TRUNCATE
    // cannot be rolled back anyway.
    if ($dry) { $db->beginTransaction(); }
    $report = itqan_scrub($db, $opts);
    // Operator guard: if no admin was set, every password is locked behind a
    // random hash from step 2 — no one can log in until an admin is restored.
    if (($opts['admin_username'] === '' || $opts['admin_password'] === '')
        && !isset($report['admin'])) {
        echo "WARNING: no admin credentials set — every password is locked behind a random hash\n";
    }
    if ($dry) { $db->rollBack(); echo "DRY-RUN (rolled back)\n"; }
    else { echo "SCRUB COMMITTED\n"; }
    foreach ($report as $k => $v) { echo "  $k: $v\n"; }
    return 0;
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && basename((string) $argv[0]) === basename(__FILE__)) {
    exit(itqan_scrub_main($argv));
}
