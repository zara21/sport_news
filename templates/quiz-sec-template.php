<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth_helpers.php';

$pageTitle = 'ქვიზი';
$quiz = null;
$questions = [];
$errorMessage = '';
$statLabels = [
    'goals' => 'გოლი',
    'assists' => 'ასისტი',
    'appearances' => 'მატჩი',
    'titles' => 'ტიტული',
    'minutes' => 'წუთი',
];
$statLabel = 'სტატისტიკა';

if (empty($slug)) {
    $errorMessage = 'ქვიზის იდენტიფიკატორი ვერ მოიძებნა.';
} else {
    $stmt = $pdo->prepare('SELECT * FROM quiz_games WHERE slug = ?');
    $stmt->execute([$slug]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quiz) {
        $errorMessage = 'ქვიზი ვერ მოიძებნა.';
    } else {
        $pageTitle = $quiz['title'] ?: $pageTitle;
        $statLabel = $statLabels[$quiz['stat_type']] ?? $statLabel;

        $stmt = $pdo->prepare('SELECT * FROM quiz_game_questions WHERE quiz_game_id = ? ORDER BY stat_value DESC, id ASC');
        $stmt->execute([$quiz['id']]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$duration = max(1, (int)($quiz['duration_minutes'] ?? 5));
$quizImage = !empty($quiz['image']) ? '/sport_blog/uploads/' . ltrim($quiz['image'], '/') : '';
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> - Sport Blog</title>
    <link rel="stylesheet" href="/sport_blog/css/styles.css?v=20261119d">
    <link rel="stylesheet" href="/sport_blog/css/navbar.css?v=20261119d">
    <link rel="stylesheet" href="/sport_blog/css/quiz.css?v=20261119d">
    <link rel="stylesheet" href="/sport_blog/assets/vendor/fontawesome/css/all.min.css">
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>

<main class="quiz-page">
    <section class="quiz-shell quiz-shell--game">
        <?php if ($errorMessage): ?>
            <div class="quiz-auth-required">
                <span class="quiz-kicker">შეცდომა</span>
                <h1><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></h1>
                <a class="quiz-button quiz-button--primary" href="/sport_blog/views/all_quizzes">ქვიზებზე დაბრუნება</a>
            </div>
        <?php else: ?>
            <article class="quiz-game-panel">
                <div class="quiz-game-main">
                    <div class="quiz-heading">
                        <span class="quiz-kicker">გამოსაცნობი ქვიზი</span>
                        <h1><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                    </div>

                    <?php if ($quizImage): ?>
                        <img class="quiz-game-image" src="<?= htmlspecialchars($quizImage, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>">
                    <?php endif; ?>

                    <div class="quiz-game-meta">
                        <span><i class="far fa-clock"></i> დრო: <strong id="time"></strong></span>
                        <span><i class="fas fa-list-ol"></i> სულ: <strong><?= count($questions) ?></strong></span>
                    </div>

                    <div class="quiz-answer-row">
                        <input class="quiz-answer-input" type="text" id="answerInput" placeholder="მაგ: Cristiano Ronaldo" autocomplete="off">
                        <button class="quiz-button quiz-button--primary quiz-submit-btn" type="button" onclick="submitAnswer()">დადასტურება</button>
                    </div>
                    <p class="quiz-feedback" id="feedback" aria-live="polite"></p>
                </div>

                <div class="quiz-game-side">
                    <div class="quiz-score">
                        გამოცნობილია: <span id="correctCount">0</span> / <span id="total"><?= count($questions) ?></span>
                    </div>
                    <div class="quiz-table-wrap">
                        <table class="quiz-stat-table">
                            <thead>
                                <tr>
                                    <th><?= htmlspecialchars($statLabel, ENT_QUOTES, 'UTF-8') ?></th>
                                    <th>მოთამაშე</th>
                                </tr>
                            </thead>
                            <tbody id="playerTable"></tbody>
                        </table>
                    </div>
                </div>
            </article>
        <?php endif; ?>
    </section>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>

<?php if (!$errorMessage): ?>
<script>
const players = <?= json_encode($questions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
let guessed = Array(players.length).fill(false);
let finished = false;
let correctCount = 0;
const gameId = <?= (int)($quiz['id'] ?? 0) ?>;
const resultCsrf = <?= json_encode(!empty($_SESSION['user_id']) ? form_csrf_token() : '') ?>;

const tableBody = document.getElementById('playerTable');
players.forEach((player, index) => {
    const row = document.createElement('tr');
    row.innerHTML = `
        <td></td>
        <td id="playerCell${index}"></td>
    `;
    row.firstElementChild.textContent = player.stat_value ?? "";
    tableBody.appendChild(row);
});

function normalize(value) {
    return String(value || '').trim().toLowerCase();
}

function submitAnswer() {
    if (finished) return;

    const input = document.getElementById('answerInput');
    const feedback = document.getElementById('feedback');
    const guess = normalize(input.value);
    feedback.textContent = '';

    if (!guess) return;

    let found = false;
    players.forEach((player, index) => {
        if (guessed[index]) return;

        if (normalize(player.player_name_geo) === guess || normalize(player.player_name_eng) === guess) {
            const cell = document.getElementById('playerCell' + index);
            cell.textContent = player.player_name_geo;
            cell.classList.add('correct');
            guessed[index] = true;
            correctCount++;
            found = true;
        }
    });

    if (found) {
        input.value = '';
        document.getElementById('correctCount').textContent = correctCount;
        if (correctCount === players.length) finishGame('ყველა მოთამაშე გამოცნობილია.');
    } else {
        feedback.textContent = 'მოთამაშე ვერ მოიძებნა ან შეცდომითაა შეყვანილი.';
        input.classList.add('incorrect');
        setTimeout(() => input.classList.remove('incorrect'), 800);
    }
}

document.getElementById('answerInput').addEventListener('keydown', (event) => {
    if (event.key === 'Enter') submitAnswer();
});

let time = <?= $duration ?> * 60;
const timeDisplay = document.getElementById('time');

function updateTimer() {
    const minutes = Math.floor(time / 60);
    const seconds = time % 60;
    timeDisplay.textContent = `${minutes}:${String(seconds).padStart(2, '0')}`;

    if (time === 0) {
        finishGame('დრო ამოიწურა. ქვიზი დასრულდა.');
        return;
    }

    time--;
}

const timer = setInterval(updateTimer, 1000);
updateTimer();

function finishGame(message) {
    if (finished) return;
    finished = true;
    clearInterval(timer);

    const input = document.getElementById('answerInput');
    input.disabled = true;
    document.querySelector('.quiz-submit-btn').disabled = true;

    players.forEach((player, index) => {
        if (!guessed[index]) {
            const cell = document.getElementById('playerCell' + index);
            cell.textContent = player.player_name_geo;
            cell.classList.add('missed');
        }
    });

    document.getElementById('feedback').textContent = message;
    if (resultCsrf) {
        fetch('/sport_blog/api/save_quiz_game_result.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-Token':resultCsrf},
            body: JSON.stringify({game_id: gameId, guessed_ids: players.filter((_, index) => guessed[index]).map(player => player.id)})
        }).catch(() => {});
    }
}
</script>
<?php endif; ?>
</body>
</html>
