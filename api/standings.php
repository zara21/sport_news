<?php
require_once '../includes/db_connect.php';

if (isset($_GET['league'])) {
    $league = $_GET['league'];
    $allowed = ['premier_league', 'la_liga', 'serie_a', 'ligue_1', 'bundesliga'];
    if (!in_array($league, $allowed)) {
        http_response_code(400);
        echo json_encode(['error' => 'არასწორი ლიგა.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT position, team, played, wins, draws, losses, score, goal_diff, points 
                           FROM league_standings 
                           WHERE league_name = ? 
                           ORDER BY position ASC");
    $stmt->execute([$league]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
    exit;
}
?>
