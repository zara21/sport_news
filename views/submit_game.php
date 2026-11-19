<?php
include '../includes/db_connect.php';

$game_id = $_POST['game_id'];
$answers = $_POST['answers'] ?? [];

$stmt = $pdo->prepare("SELECT * FROM game_players WHERE game_id = ?");
$stmt->execute([$game_id]);
$players = $stmt->fetchAll();

$score = 0;

foreach ($players as $index => $player) {
    $userInput = trim(strtolower($answers[$index]));
    if ($userInput === strtolower($player['name_ka']) || $userInput === strtolower($player['name_en'])) {
        $score++;
    }
}

$stmt = $pdo->prepare("INSERT INTO user_attempts (game_id, username, answers, score) VALUES (?, ?, ?, ?)");
$stmt->execute([$game_id, 'Guest', json_encode($answers), $score]);

echo "შენი ქულა: $score/" . count($players);
