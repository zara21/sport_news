<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/home_match_helpers.php';

header('Content-Type: application/json; charset=UTF-8');
$offset = max(0, (int) ($_GET['offset'] ?? 0));
$limit = min(20, max(1, (int) ($_GET['limit'] ?? 12)));
$allMatches = home_match_feed($pdo);
$matches = array_slice($allMatches, $offset, $limit);
ob_start();
foreach ($matches as $match) {
    require __DIR__ . '/match_card.php';
}
echo json_encode(['html' => ob_get_clean(), 'count' => count($matches), 'total' => count($allMatches)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
