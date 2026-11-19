<?php
require_once __DIR__ . '/../includes/auth_helpers.php';
auth_start_session();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/quiz_attempts.php';

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
        http_response_code(404);
    } else {
        $pageTitle = $quiz['title'] ?: $pageTitle;
        $statLabel = $statLabels[$quiz['stat_type']] ?? $statLabel;

        $stmt = $pdo->prepare('SELECT player_name_geo, player_name_eng, stat_value
            FROM quiz_game_questions WHERE quiz_game_id = ? ORDER BY stat_value DESC, id ASC');
        $stmt->execute([$quiz['id']]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$duration = max(1, (int)($quiz['duration_minutes'] ?? 5));
$gameAttempt = $questions ? start_quiz_attempt('game_quiz_attempts', [
    'game_id' => (int) $quiz['id'],
    'user_id' => (int) ($_SESSION['user_id'] ?? 0),
    'ends_at' => time() + $duration * 60,
    'players' => $questions,
    'guessed' => [],
]) : '';
$quizImage = !empty($quiz['image']) ? '/sport_blog/uploads/' . ltrim($quiz['image'], '/') : '';
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php require __DIR__ . '/../includes/head_meta.php'; ?>
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> - Sport Blog</title>
    <link rel="stylesheet" href="/sport_blog/css/styles.css?v=20261119g">
    <link rel="stylesheet" href="/sport_blog/css/navbar.css?v=20261119g">
    <link rel="stylesheet" href="/sport_blog/css/quiz.css?v=20261119g">
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
                        <input class="quiz-answer-input" type="text" id="answerInput" placeholder="ჩაწერეთ მოთამაშის სახელი" autocomplete="off" maxlength="255" aria-label="მოთამაშის სახელი">
                        <button class="quiz-button quiz-button--primary quiz-submit-btn" type="button" onclick="submitAnswer()">დადასტურება</button>
                    </div>
                    <p class="quiz-feedback" id="feedback" aria-live="polite"></p>
                    <button class="quiz-button quiz-button--ghost" type="button" id="retryResult" hidden>ხელახლა ცდა</button>
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
const stats = <?= json_encode(array_column($questions, 'stat_value')) ?>;
const attempt = <?= json_encode($gameAttempt) ?>;
const csrf = <?= json_encode(form_csrf_token()) ?>;
const input = document.getElementById('answerInput');
const submitButton = document.querySelector('.quiz-submit-btn');
const retryButton = document.getElementById('retryResult');
const feedback = document.getElementById('feedback');
const timeDisplay = document.getElementById('time');
const countDisplay = document.getElementById('correctCount');
const tableBody = document.getElementById('playerTable');
let deadline = performance.now() + <?= $duration ?> * 60000;
let pending = false;
let finished = false;

const cells = stats.map((value) => {
    const row = tableBody.insertRow();
    row.insertCell().textContent = value ?? '';
    return row.insertCell();
});

function showResult(result) {
    finished = true;
    clearInterval(timer);
    input.disabled = true;
    submitButton.disabled = true;
    countDisplay.textContent = result.score;
    result.players.forEach((player, index) => {
        cells[index].textContent = player.name;
        cells[index].classList.add(player.guessed ? 'correct' : 'missed');
    });
    if (result.score < result.total) timeDisplay.textContent = '0:00';
    feedback.textContent = result.score === result.total
        ? 'ყველა მოთამაშე გამოცნობილია.'
        : 'დრო ამოიწურა. ქვიზი დასრულდა.';
}

async function requestGame(action, guess) {
    pending = true;
    submitButton.disabled = true;
    retryButton.hidden = true;
    try {
        const response = await fetch('/sport_blog/api/save_quiz_game_result.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify({ attempt, action, guess })
        });
        const result = await response.json();
        if (Number.isInteger(result.remaining)) {
            deadline = performance.now() + result.remaining * 1000;
            input.disabled = false;
            return;
        }
        if (!response.ok) throw new Error('Quiz request failed');

        if (result.finished) {
            showResult(result);
            return;
        }

        result.matches.forEach(({ index, name }) => {
            cells[index].textContent = name;
            cells[index].classList.add('correct');
        });
        countDisplay.textContent = result.score;
        if (result.matches.length) {
            if (input.value.trim() === guess) input.value = '';
            feedback.textContent = '';
        } else {
            feedback.textContent = 'მოთამაშე ვერ მოიძებნა ან შეცდომითაა შეყვანილი.';
        }
    } catch {
        feedback.textContent = 'კავშირი შეფერხდა. სცადეთ ხელახლა.';
        if (action === 'finish') retryButton.hidden = false;
    } finally {
        pending = false;
        submitButton.disabled = finished || input.disabled;
    }
    if (!finished && Number(countDisplay.textContent) === stats.length && retryButton.hidden) {
        finishGame();
    }
}

function submitAnswer() {
    const guess = input.value.trim();
    if (finished || pending || input.disabled || !guess) return;
    requestGame('guess', guess);
}

function finishGame() {
    if (finished || pending) return;
    input.disabled = true;
    requestGame('finish');
}

function updateTimer() {
    const seconds = Math.max(0, Math.ceil((deadline - performance.now()) / 1000));
    timeDisplay.textContent = Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0');
    if (seconds === 0 && retryButton.hidden) finishGame();
}

input.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') submitAnswer();
});
retryButton.addEventListener('click', finishGame);

const timer = attempt ? setInterval(updateTimer, 1000) : null;
if (timer) {
    updateTimer();
} else {
    input.disabled = true;
    submitButton.disabled = true;
    timeDisplay.textContent = '—';
    feedback.textContent = 'ამ ქვიზს ჯერ კითხვები არ აქვს დამატებული.';
}
</script>
<?php endif; ?>
</body>
</html>
