<?php
// Run: php scripts/staging-scrub.test.php   (SQLite in-memory + temp file; no DB server)
require __DIR__ . '/staging-scrub.php';
$fail = 0;
function ok(string $name, bool $cond): void { global $fail; echo ($cond ? 'PASS ' : 'FAIL ') . $name . "\n"; if (!$cond) { $fail = 1; } }

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, email TEXT, password TEXT, avatar_url TEXT)');
$db->exec('CREATE TABLE access_tokens (token TEXT)');
$db->exec('CREATE TABLE jobs (id INTEGER)');
$db->exec('CREATE TABLE posts (id INTEGER, content TEXT)');
$db->exec("INSERT INTO users VALUES (1,'admin','admin@itqan.dev','HASH','https://community.itqan.dev/av/1.png')");
$db->exec("INSERT INTO users VALUES (2,'a','a@real.com','H2','https://community.itqan.dev/av/2.png')");
$db->exec("INSERT INTO access_tokens VALUES ('tok')");
$db->exec("INSERT INTO jobs VALUES (1)");
$db->exec("INSERT INTO posts VALUES (1,'see https://community.itqan.dev/d/1 ok')");

$r = itqan_scrub($db, [
    'from_host' => 'https://community.itqan.dev',
    'to_host' => 'https://staging.community.itqan.dev',
    'admin_username' => 'admin', 'admin_password' => 'pw',
]);

$emails = $db->query('SELECT email FROM users ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
ok('emails faked', $emails === ['u1@staging.invalid', 'u2@staging.invalid']);
ok('non-admin passwords nulled', (int) $db->query('SELECT COUNT(*) FROM users WHERE password IS NOT NULL')->fetchColumn() === 1);
ok('access_tokens purged', (int) $db->query('SELECT COUNT(*) FROM access_tokens')->fetchColumn() === 0);
ok('jobs purged', (int) $db->query('SELECT COUNT(*) FROM jobs')->fetchColumn() === 0);
ok('content host rewritten', str_contains((string) $db->query('SELECT content FROM posts WHERE id=1')->fetchColumn(), 'staging.community.itqan.dev'));
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
$boot->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, email TEXT, password TEXT, avatar_url TEXT)");
$boot->exec("INSERT INTO users VALUES (1,'real','real@itqan.dev','H','https://community.itqan.dev/av/1.png')");
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

exit($fail);
