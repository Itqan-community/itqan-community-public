<?php
// Purge/rewrite the private + active fields on the STAGING database after a
// prod-content import. Portable across MySQL (server) and SQLite (tests).
// NEVER run against production. Usage:
//   php staging-scrub.php /var/www/flarum/config.php [--dry-run]
// Env: SCRUB_DSN / SCRUB_USER / SCRUB_PASS (override config), SCRUB_FROM_HOST,
//      SCRUB_TO_HOST, STAGING_ADMIN_USERNAME, STAGING_ADMIN_PASSWORD.

function itqan_table_exists(PDO $db, string $t): bool
{
    try { $db->query("SELECT 1 FROM `$t` LIMIT 0"); return true; }
    catch (PDOException $e) { return false; }
}

function itqan_col_exists(PDO $db, string $t, string $c): bool
{
    try { $db->query("SELECT `$c` FROM `$t` LIMIT 0"); return true; }
    catch (PDOException $e) { return false; }
}

function itqan_scrub(PDO $db, array $o = []): array
{
    $purge = ['access_tokens', 'email_tokens', 'password_tokens',
              'login_providers', 'push_subscriptions', 'jobs', 'failed_jobs'];
    $suffix = $o['email_suffix'] ?? '@staging.invalid';
    $report = [];

    // 1. Kill impersonation + device + queued-work channels first.
    foreach ($purge as $t) {
        if (!itqan_table_exists($db, $t)) { $report[$t] = 'absent'; continue; }
        $report[$t] = 'purged:' . $db->exec("DELETE FROM `$t`");
    }

    // 2. Fake every email; null every password (loop = portable, ~2k rows).
    if (itqan_table_exists($db, 'users') && itqan_col_exists($db, 'users', 'email')) {
        $hasPw = itqan_col_exists($db, 'users', 'password');
        $sql = "UPDATE `users` SET email = ?" . ($hasPw ? ", password = NULL" : "") . " WHERE id = ?";
        $up = $db->prepare($sql);
        $ids = $db->query("SELECT id FROM `users`")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) { $up->execute(["u{$id}{$suffix}", $id]); }
        $report['users_email'] = 'faked:' . count($ids);
    }

    // 3. Rewrite prod hostnames in stored content so staging self-hosts.
    $from = $o['from_host'] ?? ''; $to = $o['to_host'] ?? '';
    if ($from !== '' && $to !== '' && $from !== $to) {
        foreach ([['posts', 'content'], ['users', 'avatar_url']] as [$t, $c]) {
            if (itqan_table_exists($db, $t) && itqan_col_exists($db, $t, $c)) {
                $st = $db->prepare("UPDATE `$t` SET `$c` = REPLACE(`$c`, ?, ?)");
                $st->execute([$from, $to]);
                $report["rewrite:$t.$c"] = $st->rowCount();
            }
        }
    }

    // 4. Restore a staging-only admin login.
    $u = $o['admin_username'] ?? ''; $p = $o['admin_password'] ?? '';
    if ($u !== '' && $p !== '') {
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
        'admin_username' => getenv('STAGING_ADMIN_USERNAME') ?: '',
        'admin_password' => getenv('STAGING_ADMIN_PASSWORD') ?: '',
    ];
    $db->beginTransaction();
    $report = itqan_scrub($db, $opts);
    if ($dry) { $db->rollBack(); echo "DRY-RUN (rolled back)\n"; }
    else { $db->commit(); echo "SCRUB COMMITTED\n"; }
    foreach ($report as $k => $v) { echo "  $k: $v\n"; }
    return 0;
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    exit(itqan_scrub_main($argv));
}
