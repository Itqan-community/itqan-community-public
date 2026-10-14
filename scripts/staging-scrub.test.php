<?php
// Run: php scripts/staging-scrub.test.php   (SQLite in-memory + temp file; no DB server)
require __DIR__ . '/staging-scrub.php';
$fail = 0;
function ok(string $name, bool $cond): void { global $fail; echo ($cond ? 'PASS ' : 'FAIL ') . $name . "\n"; if (!$cond) { $fail = 1; } }

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, email TEXT, password TEXT NOT NULL, avatar_url TEXT)');
$db->exec('CREATE TABLE access_tokens (token TEXT)');
$db->exec('CREATE TABLE jobs (id INTEGER)');
$db->exec('CREATE TABLE queue_jobs (id INTEGER)');
$db->exec('CREATE TABLE queue_failed_jobs (id INTEGER)');
$db->exec('CREATE TABLE posts (id INTEGER, content TEXT)');
$db->exec("CREATE TABLE settings (`key` TEXT PRIMARY KEY, `value` TEXT)");
// Extra fixture tables added after staging import audit. Each carries a row
// that must be purged by itqan_scrub().
$db->exec('CREATE TABLE html_headers (id INTEGER, header TEXT)');
$db->exec('CREATE TABLE webhooks (id INTEGER, url TEXT)');
$db->exec('CREATE TABLE api_keys (id INTEGER, `key` TEXT)');
$db->exec('CREATE TABLE mailerlite_subscribers (id INTEGER, email TEXT)');
$db->exec('CREATE TABLE registration_tokens (token TEXT)');
$db->exec('CREATE TABLE discussion_views (id INTEGER, ip TEXT)');
$adminFixtureHash = password_hash('fixture-admin-pw', PASSWORD_BCRYPT);
$userFixtureHash = password_hash('fixture-user-pw', PASSWORD_BCRYPT);
$db->exec("INSERT INTO users VALUES (1,'admin','admin@itqan.dev','{$adminFixtureHash}','https://community.itqan.dev/av/1.png')");
$db->exec("INSERT INTO users VALUES (2,'a','a@real.com','{$userFixtureHash}','https://community.itqan.dev/av/2.png')");
$db->exec("INSERT INTO access_tokens VALUES ('tok')");
$db->exec("INSERT INTO jobs VALUES (1)");
$db->exec("INSERT INTO queue_jobs VALUES (1),(2)");
$db->exec("INSERT INTO queue_failed_jobs VALUES (1)");
$db->exec("INSERT INTO posts VALUES (1,'see https://community.itqan.dev/d/1 ok')");
$db->exec("INSERT INTO settings (`key`,`value`) VALUES ('mail_driver','smtp'),('mail_password','SECRET'),('fof-analytics.tracking_id','G-XXX'),('forum_title','Itqan')");
$db->exec("INSERT INTO html_headers VALUES (1, '<script>ga4-prod</script>')");
$db->exec("INSERT INTO webhooks VALUES (1, 'https://prod.example.com/hook')");
$db->exec("INSERT INTO api_keys VALUES (1, 'prod-secret-key')");
$db->exec("INSERT INTO mailerlite_subscribers VALUES (1, 'real@prod.example.com')");
$db->exec("INSERT INTO registration_tokens VALUES ('tok')");
$db->exec("INSERT INTO discussion_views VALUES (1, '203.0.113.42')");

$r = itqan_scrub($db, [
    'from_host' => 'https://community.itqan.dev',
    'to_host' => 'https://staging.community.itqan.dev',
    'admin_username' => 'admin', 'admin_password' => 'pw',
]);

$emails = $db->query('SELECT email FROM users ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
ok('emails faked', $emails === ['u1@staging.invalid', 'u2@staging.invalid']);
$nonAdminHashes = $db->query("SELECT password FROM users WHERE username <> 'admin' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
ok('non-admin passwords non-null', count($nonAdminHashes) > 0 && array_product(array_map(static fn($h) => $h !== null && $h !== '' ? 1 : 0, $nonAdminHashes)) === 1);
ok('non-admin passwords share one lock hash', count(array_unique($nonAdminHashes)) === 1);
ok('non-admin lock hash differs from fixture', reset($nonAdminHashes) !== $userFixtureHash);
$adminHash = (string) $db->query("SELECT password FROM users WHERE username = 'admin'")->fetchColumn();
ok('admin password non-null and verifies', $adminHash !== '' && password_verify('pw', $adminHash));
ok('access_tokens purged', (int) $db->query('SELECT COUNT(*) FROM access_tokens')->fetchColumn() === 0);
ok('jobs purged', (int) $db->query('SELECT COUNT(*) FROM jobs')->fetchColumn() === 0);
ok('queue_jobs purged', (int) $db->query('SELECT COUNT(*) FROM queue_jobs')->fetchColumn() === 0);
ok('queue_failed_jobs purged', (int) $db->query('SELECT COUNT(*) FROM queue_failed_jobs')->fetchColumn() === 0);
ok('html_headers purged', (int) $db->query('SELECT COUNT(*) FROM html_headers')->fetchColumn() === 0);
ok('webhooks purged', (int) $db->query('SELECT COUNT(*) FROM webhooks')->fetchColumn() === 0);
ok('api_keys purged', (int) $db->query('SELECT COUNT(*) FROM api_keys')->fetchColumn() === 0);
ok('mailerlite_subscribers purged', (int) $db->query('SELECT COUNT(*) FROM mailerlite_subscribers')->fetchColumn() === 0);
ok('registration_tokens purged', (int) $db->query('SELECT COUNT(*) FROM registration_tokens')->fetchColumn() === 0);
ok('discussion_views purged', (int) $db->query('SELECT COUNT(*) FROM discussion_views')->fetchColumn() === 0);
ok('content host rewritten', str_contains((string) $db->query('SELECT content FROM posts WHERE id=1')->fetchColumn(), 'staging.community.itqan.dev'));
$mailDriver = (string) $db->query("SELECT `value` FROM settings WHERE `key`='mail_driver'")->fetchColumn();
ok('mail_driver forced to log', $mailDriver === 'log');
ok('mail_password deleted', (int) $db->query("SELECT COUNT(*) FROM settings WHERE `key`='mail_password'")->fetchColumn() === 0);
ok('fof-analytics.tracking_id deleted', (int) $db->query("SELECT COUNT(*) FROM settings WHERE `key`='fof-analytics.tracking_id'")->fetchColumn() === 0);
$forumTitle = (string) $db->query("SELECT `value` FROM settings WHERE `key`='forum_title'")->fetchColumn();
ok('forum_title unchanged', $forumTitle === 'Itqan');
$avatars = $db->query('SELECT avatar_url FROM users ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
ok('avatar_url rewritten', $avatars === [
    'https://staging.community.itqan.dev/av/1.png',
    'https://staging.community.itqan.dev/av/2.png',
]);
ok('absent tables skipped', ($r['failed_jobs'] ?? '') === 'absent');
ok('admin restored', ($r['admin'] ?? '') === 'set:admin');

// --dry-run + SCRUB_DSN end-to-end against a real SQLite FILE.
// Dry-run must roll back: the email stays real; a WARNING is printed because
// no admin credentials are set. Then a real run must commit the faked email.
$tmpdir = sys_get_temp_dir();
$file = tempnam($tmpdir, 'scrub');
$boot = new PDO('sqlite:' . $file);
$boot->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$boot->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, email TEXT, password TEXT NOT NULL, avatar_url TEXT)");
$boot->exec("CREATE TABLE settings (`key` TEXT PRIMARY KEY, `value` TEXT)");
$boot->exec("INSERT INTO users VALUES (1,'real','real@itqan.dev','" . password_hash('fixture-pw', PASSWORD_BCRYPT) . "','https://community.itqan.dev/av/1.png')");
$boot->exec("INSERT INTO settings (`key`,`value`) VALUES ('mail_driver','smtp'),('mail_password','SECRET'),('fof-analytics.tracking_id','G-XXX'),('forum_title','Itqan')");
$boot = null; // close so the file is releasable on Windows
register_shutdown_function(function () use ($file) { if (is_file($file)) { @unlink($file); } });

putenv('SCRUB_DSN=sqlite:' . $file);
putenv('SCRUB_FROM_HOST=https://community.itqan.dev');
putenv('SCRUB_TO_HOST=https://staging.community.itqan.dev');
putenv('STAGING_ADMIN_USERNAME=');
putenv('STAGING_ADMIN_PASSWORD=');

$out = [];
$rc = 0;
ob_start();
$rc = itqan_scrub_main(['staging-scrub.php', 'unused-config', '--dry-run']);
$dryOut = ob_get_clean();
ok('dry-run exits 0', $rc === 0);
ok('dry-run prints DRY-RUN', str_contains($dryOut, 'DRY-RUN (rolled back)'));
ok('dry-run prints WARNING', str_contains($dryOut, 'WARNING: no admin credentials set'));

$reopen = new PDO('sqlite:' . $file);
$reopen->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$stillReal = (string) $reopen->query("SELECT email FROM users WHERE id=1")->fetchColumn();
ok('dry-run rolled back (email unchanged)', $stillReal === 'real@itqan.dev');
$dryMailDriver = (string) $reopen->query("SELECT `value` FROM settings WHERE `key`='mail_driver'")->fetchColumn();
ok('dry-run rolled back (mail_driver still smtp)', $dryMailDriver === 'smtp');
$dryMailPw = (int) $reopen->query("SELECT COUNT(*) FROM settings WHERE `key`='mail_password'")->fetchColumn();
ok('dry-run rolled back (mail_password still present)', $dryMailPw === 1);
$dryAnalytics = (int) $reopen->query("SELECT COUNT(*) FROM settings WHERE `key`='fof-analytics.tracking_id'")->fetchColumn();
ok('dry-run rolled back (fof-analytics.tracking_id still present)', $dryAnalytics === 1);
$dryForumTitle = (string) $reopen->query("SELECT `value` FROM settings WHERE `key`='forum_title'")->fetchColumn();
ok('dry-run rolled back (forum_title unchanged)', $dryForumTitle === 'Itqan');
$reopen = null;

ob_start();
$rc = itqan_scrub_main(['staging-scrub.php', 'unused-config']);
$liveOut = ob_get_clean();
ok('live run exits 0', $rc === 0);
ok('live run prints SCRUB COMMITTED', str_contains($liveOut, 'SCRUB COMMITTED'));

$reopen2 = new PDO('sqlite:' . $file);
$reopen2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$nowFaked = (string) $reopen2->query("SELECT email FROM users WHERE id=1")->fetchColumn();
ok('live run faked the email', $nowFaked === 'u1@staging.invalid');
$reopen2 = null;

putenv('SCRUB_DSN');
putenv('SCRUB_FROM_HOST');
putenv('SCRUB_TO_HOST');
putenv('STAGING_ADMIN_USERNAME');
putenv('STAGING_ADMIN_PASSWORD');

// Production-host denylist guard.
// To verify the message actually goes to STDERR (not STDOUT) without the
// inline call leaking the REFUSING line into the test runner's own stderr,
// every guard assertion is run in a subprocess with split stdout/stderr.
$prodCfg = tempnam($tmpdir, 'prodcfg');
file_put_contents($prodCfg, "<?php return ['database' => ['host' => '10.106.0.5', 'database' => 'flarum', 'username' => 'u', 'password' => 'p']];");
register_shutdown_function(function () use ($prodCfg) { if (is_file($prodCfg)) { @unlink($prodCfg); } });

$stgCfg = tempnam($tmpdir, 'stgcfg');
file_put_contents($stgCfg, "<?php return ['database' => ['host' => '10.107.0.2', 'database' => 'flarum', 'username' => 'u', 'password' => 'p']];");
register_shutdown_function(function () use ($stgCfg) { if (is_file($stgCfg)) { @unlink($stgCfg); } });

// Case A: production host + default denylist -> exit 2, REFUSING on stderr.
$probeA = tempnam($tmpdir, 'probeA');
file_put_contents($probeA, "<?php require " . var_export(__DIR__ . '/staging-scrub.php', true) . "; putenv('SCRUB_DSN'); putenv('SCRUB_DENY_HOSTS'); exit(itqan_scrub_main(['staging-scrub.php', " . var_export($prodCfg, true) . "]));");
register_shutdown_function(function () use ($probeA) { if (is_file($probeA)) { @unlink($probeA); } });
$outA = $tmpdir . '/Aout'; $errA = $tmpdir . '/Aerr';
$cmdA = escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr ' . escapeshellarg($probeA) . ' 1>' . escapeshellarg($outA) . ' 2>' . escapeshellarg($errA);
$rcA = 0; $soA = ''; $seA = '';
exec($cmdA, $unused, $rcA);
$soA = (string) file_get_contents($outA);
$seA = (string) file_get_contents($errA);
@unlink($outA); @unlink($errA);
ok('denylist: production host refused (rc=2)', $rcA === 2);
ok('denylist: REFUSING on stderr', str_contains($seA, 'REFUSING: host 10.106.0.5 is a production host'));
ok('denylist: no REFUSING on stdout', !str_contains($soA, 'REFUSING:'));

// Case B: staging host with overridden denylist (10.106.0.5 only) -> guard
// must pass; the call will then fail at MySQL connect, surfacing as rc -99
// from the probe (via a caught PDOException).
$probeB = tempnam($tmpdir, 'probeB');
file_put_contents($probeB, "<?php require " . var_export(__DIR__ . '/staging-scrub.php', true) . "; putenv('SCRUB_DSN'); putenv('SCRUB_DENY_HOSTS=10.106.0.5'); try { exit(itqan_scrub_main(['staging-scrub.php', " . var_export($stgCfg, true) . "])); } catch (PDOException \$e) { fwrite(STDERR, 'POST_GUARD:' . \$e->getMessage()); exit(99); }");
register_shutdown_function(function () use ($probeB) { if (is_file($probeB)) { @unlink($probeB); } });
$outB = $tmpdir . '/Bout'; $errB = $tmpdir . '/Berr';
$cmdB = escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr ' . escapeshellarg($probeB) . ' 1>' . escapeshellarg($outB) . ' 2>' . escapeshellarg($errB);
$rcB = 0; $soB = ''; $seB = '';
exec($cmdB, $unused, $rcB);
$soB = (string) file_get_contents($outB);
$seB = (string) file_get_contents($errB);
@unlink($outB); @unlink($errB);
ok('denylist: staging host not refused (rc != 2)', $rcB !== 2);
ok('denylist: staging host did not emit REFUSING', !str_contains($soB . $seB, 'REFUSING:'));
ok('denylist: guard passed, failure was post-guard (rc=99, POST_GUARD on stderr)',
    $rcB === 99 && str_contains($seB, 'POST_GUARD:'));

putenv('SCRUB_DENY_HOSTS');

exit($fail);
