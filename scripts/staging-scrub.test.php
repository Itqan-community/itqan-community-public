<?php
// Run: php scripts/staging-scrub.test.php   (SQLite in-memory; no DB server)
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
ok('absent tables skipped', ($r['failed_jobs'] ?? '') === 'absent');
ok('admin restored', ($r['admin'] ?? '') === 'set:admin');
exit($fail);
