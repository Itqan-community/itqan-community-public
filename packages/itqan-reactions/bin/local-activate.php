<?php

$root = '/var/www/html';

// Fix autoload_psr4.php key
$psr4Path = "$root/vendor/composer/autoload_psr4.php";
$psr4 = file_get_contents($psr4Path);
$psr4 = str_replace("'Itqan\\\\Reactions\\\\'", "'Itqan\\Reactions\\'", $psr4);
// Also handle the quadrupled form if present as literal in file
$psr4 = str_replace("Itqan\\\\Reactions\\\\'", "Itqan\\Reactions\\'", $psr4);
file_put_contents($psr4Path, $psr4);
echo "psr4 file rewritten\n";

$a = require $psr4Path;
echo 'Reactions key present: ' . (isset($a['Itqan\\Reactions\\']) ? 'yes' : 'no') . "\n";
if (! isset($a['Itqan\\Reactions\\'])) {
    // Force-add
    $a['Itqan\\Reactions\\'] = [$root . '/vendor/itqan/flarum-reactions/src'];
    $export = var_export($a, true);
    $export = str_replace("'" . $root . "/vendor", '$vendorDir . \'', $export);
    // simpler write:
    file_put_contents($psr4Path, "<?php\n\n// autoload_psr4.php @generated\n\n\$vendorDir = dirname(__DIR__);\n\$baseDir = dirname(\$vendorDir);\n\nreturn " . var_export($a, true) . ";\n");
    // Restore $vendorDir style for consistency — keep absolute paths; ClassLoader accepts them
    echo "force-added Reactions to psr4\n";
}

// Fix ClassLoader static init if needed
$staticPath = "$root/vendor/composer/autoload_static.php";
if (is_file($staticPath)) {
    $static = file_get_contents($staticPath);
    if (strpos($static, 'Itqan\\Reactions\\') === false && strpos($static, 'Itqan\\\\Reactions\\\\') !== false) {
        $static = str_replace('Itqan\\\\Reactions\\\\', 'Itqan\\Reactions\\', $static);
        file_put_contents($staticPath, $static);
        echo "fixed autoload_static\n";
    } elseif (strpos($static, 'Itqan\\Reactions\\') === false) {
        echo "WARNING: Reactions missing from autoload_static — will prepend loader\n";
    } else {
        echo "autoload_static already ok\n";
    }
}

require "$root/vendor/autoload.php";
echo class_exists('Itqan\\Reactions\\ReactionType') ? "class ok\n" : "class missing\n";

// Enable extension + seed via PDO
$pdo = new PDO('mysql:host=db;dbname=flarum;charset=utf8mb4', 'flarum', 'flarum');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$json = $pdo->query("SELECT value FROM settings WHERE `key`='extensions_enabled'")->fetchColumn();
$exts = json_decode($json, true) ?: [];
if (! in_array('itqan-reactions', $exts, true)) {
    array_unshift($exts, 'itqan-reactions');
    $stmt = $pdo->prepare("UPDATE settings SET value=? WHERE `key`='extensions_enabled'");
    $stmt->execute([json_encode(array_values($exts))]);
    echo "enabled itqan-reactions\n";
} else {
    echo "itqan-reactions already in extensions_enabled\n";
}

$count = (int) $pdo->query('SELECT COUNT(*) FROM itqan_reaction_types')->fetchColumn();
if ($count === 0) {
    $ins = $pdo->prepare('INSERT INTO itqan_reaction_types (identifier, emoji, label, enabled, position) VALUES (?,?,?,1,?)');
    foreach ([
        ['heart', '❤️', 'Heart', 0],
        ['thumbsup', '👍', 'Thumbs up', 1],
        ['joy', '😂', 'Joy', 2],
        ['tada', '🎉', 'Tada', 3],
        ['thinking', '🤔', 'Thinking', 4],
        ['eyes', '👀', 'Eyes', 5],
    ] as $row) {
        $ins->execute($row);
    }
    echo "seeded reaction types\n";
} else {
    echo "reaction types already seeded ($count)\n";
}

$perm = (int) $pdo->query("SELECT COUNT(*) FROM group_permission WHERE group_id=3 AND permission='discussion.itqanReact'")->fetchColumn();
if ($perm === 0) {
    $pdo->exec("INSERT INTO group_permission (group_id, permission) VALUES (3, 'discussion.itqanReact')");
    echo "permission granted\n";
}

$m = (int) $pdo->query("SELECT COUNT(*) FROM migrations WHERE migration='2026_09_13_000001_seed_default_reactions'")->fetchColumn();
if ($m === 0) {
    $pdo->exec("INSERT INTO migrations (migration, extension) VALUES ('2026_09_13_000001_seed_default_reactions', 'itqan-reactions')");
    echo "seed migration recorded\n";
}

$cols = $pdo->query("SHOW COLUMNS FROM posts LIKE 'reaction_counts'")->fetchAll();
echo 'reaction_counts: ' . (count($cols) ? 'yes' : 'NO') . "\n";
