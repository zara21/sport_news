<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/cache_helpers.php';
require_once __DIR__ . '/../includes/club_helpers.php';

header('Content-Type: application/json; charset=utf-8');

$league = $_GET['league'] ?? 'premier_league';
$allowedLeagues = ['premier_league', 'la_liga', 'serie_a', 'ligue_1', 'bundesliga'];
if (!in_array($league, $allowedLeagues, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'ლიგა არასწორია.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$standings = cache_remember('standings.v2.' . $league, 180, function () use ($pdo, $league): array {
    $stmt = $pdo->prepare("SELECT position, team, played, points
        FROM league_standings
        WHERE league_name = ?
        ORDER BY position ASC
        LIMIT 24");
    $stmt->execute([$league]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $team = trim((string) $row['team']);
        $club = canonical_club_name($team) ?: $team;
        $row['articles_url'] = site_path('/clubs/' . rawurlencode($club));
    }
    unset($row);
    return $rows;
});

echo json_encode($standings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
