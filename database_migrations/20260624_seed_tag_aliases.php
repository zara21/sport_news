<?php
require __DIR__ . '/../includes/db_connect.php';
require __DIR__ . '/../includes/club_helpers.php';

$find = $pdo->prepare('SELECT id FROM tags WHERE name = ? LIMIT 1');
$insert = $pdo->prepare('INSERT INTO tag_aliases (tag_id, alias) VALUES (?, ?) ON DUPLICATE KEY UPDATE tag_id = VALUES(tag_id)');
foreach (club_tag_alias_map() as $canonical => $aliases) {
    $find->execute([$canonical]);
    $tagId = (int) $find->fetchColumn();
    if (!$tagId) continue;
    foreach ($aliases as $alias) $insert->execute([$tagId, $alias]);
}
echo "Tag aliases seeded\n";
