<?php
// Scans the harness output for content defects. Exit 1 if any found.
$path = $argv[1] ?? '/var/www/html/storage/notif-harness.json';
$data = json_decode(file_get_contents($path), true);
$problems = [];
foreach ($data as $k => $v) {
    $v = (string) $v;
    if (preg_match('/\{[^}]*\}/', $v, $m)) {
        $problems[] = "$k: brace artifact -> {$m[0]}";
    }
    if (preg_match('/\{[a-z_]+$/i', $v, $m)) {
        $problems[] = "$k: unbalanced brace -> {$m[0]}";
    }
    if (substr($k, -5) === '|ar|' && preg_match('/^[\x00-\x7F\s]*$/', $v) && trim($v) !== '') {
        $problems[] = "$k: AR value is Latin-only";
    }
}
if ($problems) {
    echo "DEFECTS (".count($problems)."):\n";
    foreach ($problems as $p) { echo " - $p\n"; }
    exit(1);
}
echo "CLEAN: no placeholder/AR-EN defects\n";
