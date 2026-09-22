<?php

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
    echo "already enabled\n";
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
    echo "seeded types\n";
} else {
    echo "types: $count\n";
}

$perm = (int) $pdo->query("SELECT COUNT(*) FROM group_permission WHERE group_id=3 AND permission='discussion.itqanReact'")->fetchColumn();
if ($perm === 0) {
    $pdo->exec("INSERT INTO group_permission (group_id, permission) VALUES (3, 'discussion.itqanReact')");
    echo "permission ok\n";
}

$m = (int) $pdo->query("SELECT COUNT(*) FROM migrations WHERE migration='2026_09_13_000001_seed_default_reactions'")->fetchColumn();
if ($m === 0) {
    $pdo->exec("INSERT INTO migrations (migration, extension) VALUES ('2026_09_13_000001_seed_default_reactions', 'itqan-reactions')");
    echo "migration recorded\n";
}

$cols = $pdo->query("SHOW COLUMNS FROM posts LIKE 'reaction_counts'")->fetchAll();
echo 'reaction_counts: ' . (count($cols) ? 'yes' : 'NO') . "\n";
