<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth_helpers.php';

$pageTitle = 'ქვიზი';
$quiz = null;
$quizData = [];
$quizId = 0;
$quizImage = '';
$errorMessage = '';
$requiresAuth = !isset($_SESSION['user_id']);

if (empty($slug)) {
    $errorMessage = 'ქვიზის იდენტიფიკატორი ვერ მოიძებნა.';
} else {
    $stmt = $pdo->prepare('SELECT * FROM quizzes WHERE slug = ?');
    $stmt->execute([$slug]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quiz) {
        $errorMessage = 'ქვიზი ვერ მოიძებნა.';
    } else {
        $quizId = (int) $quiz['id'];
        $pageTitle = $quiz['title'] ?: $pageTitle;
        $quizImage = !empty($quiz['quiz_image']) ? '/sport_blog/uploads/quizzes/' . ltrim($quiz['quiz_image'], '/') : '';

        $stmt = $pdo->prepare('SELECT * FROM questions WHERE quiz_id = ? ORDER BY id ASC');
        $stmt->execute([$quizId]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmtOptions = $pdo->prepare('SELECT * FROM options WHERE question_id = ? ORDER BY id ASC');
        foreach ($questions as $question) {
            $stmtOptions->execute([$question['id']]);
            $quizData[] = [
                'question_id' => (int) $question['id'],
                'question_text' => $question['question_text'],
                'question_image' => $question['quiz_image'] ?? null,
                'correct' => (int) $question['correct_option'],
                'options' => $stmtOptions->fetchAll(PDO::FETCH_ASSOC),
            ];
        }
    }
}

$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    . ($_SERVER['REQUEST_URI'] ?? '');
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> - Sport Blog</title>
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="გაიარე სპორტული ქვიზი და შეამოწმე შენი ცოდნა.">
    <?php if ($quizImage): ?>
        <meta property="og:image" content="<?= htmlspecialchars($quizImage, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <meta property="og:url" content="<?= htmlspecialchars($currentUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:type" content="website">
    <link rel="stylesheet" href="/sport_blog/css/styles.css?v=20261119e">
    <link rel="stylesheet" href="/sport_blog/css/navbar.css?v=20261119e">
    <link rel="stylesheet" href="/sport_blog/css/quiz.css?v=20261119e">
    <link rel="stylesheet" href="/sport_blog/assets/vendor/fontawesome/css/all.min.css">
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>

<main class="quiz-page">
    <section class="quiz-shell">
        <?php if ($requiresAuth): ?>
            <div class="quiz-auth-required">
                <span class="quiz-kicker">ქვიზი</span>
                <h1>ქვიზში მონაწილეობისთვის ავტორიზაციაა საჭირო</h1>
                <p>შედეგის შესანახად გაიარე ავტორიზაცია და შემდეგ დაბრუნდი ქვიზზე.</p>
                <div class="quiz-actions">
                    <a class="quiz-button quiz-button--primary" href="/sport_blog/auth_system/login">შესვლა</a>
                    <a class="quiz-button quiz-button--ghost" href="/sport_blog/views/all_quizzes">ყველა ქვიზი</a>
                </div>
            </div>
        <?php elseif ($errorMessage): ?>
            <div class="quiz-auth-required">
                <span class="quiz-kicker">შეცდომა</span>
                <h1><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></h1>
                <a class="quiz-button quiz-button--primary" href="/sport_blog/views/all_quizzes">ქვიზებზე დაბრუნება</a>
            </div>
        <?php else: ?>
            <article class="quiz-card-panel">
                <div class="quiz-heading">
                    <span class="quiz-kicker">ქვიზი</span>
                    <h1><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                </div>

                <?php if ($quizImage): ?>
                    <img class="quiz-cover" src="<?= htmlspecialchars($quizImage, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>">
                <?php endif; ?>

                <div id="quiz" class="quiz-question-area"></div>

                <button class="quiz-button quiz-button--primary quiz-next-btn" type="button" onclick="nextQuestion()">
                    შემდეგი კითხვა
                </button>

                <div class="quiz-result-box" id="resultBox" aria-live="polite"></div>
                <div class="quiz-share-box" id="shareBox">
                    <button class="quiz-button quiz-button--facebook" type="button" onclick="shareOnFacebook()">
                        <i class="fab fa-facebook-f"></i>
                        Facebook-ზე გაზიარება
                    </button>
                </div>
            </article>
        <?php endif; ?>
    </section>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>

<?php if (!$requiresAuth && !$errorMessage): ?>
<script>
const quizData = <?= json_encode($quizData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const quizId = <?= $quizId ?>;
const quizTitle = <?= json_encode($pageTitle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const quizCsrfToken = <?= json_encode(form_csrf_token(), JSON_UNESCAPED_SLASHES) ?>;
let current = 0;
let score = 0;
let answeredCurrent = false;
let resultsSent = false;
const answers = [];

function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, (ch) => ({"&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "'": "&#39;"}[ch]));
}

function questionImagePath(path) {
    if (!path) return '';
    if (/^https?:\/\//i.test(path) || (path.startsWith('/') && !path.startsWith('//'))) return path;
    return '/sport_blog/' + path.replace(/^(\.\.\/|\.\/)+/, '');
}

function loadQuestion() {
    const quizEl = document.getElementById('quiz');
    const nextBtn = document.querySelector('.quiz-next-btn');
    answeredCurrent = false;

    if (!quizData.length) {
        quizEl.innerHTML = '<p class="quiz-empty">ამ ქვიზს ჯერ კითხვები არ აქვს დამატებული.</p>';
        nextBtn.style.display = 'none';
        return;
    }

    if (current >= quizData.length) {
        quizEl.innerHTML = '';
        nextBtn.style.display = 'none';
        document.getElementById('resultBox').textContent = `თქვენი ქულა: ${score} / ${quizData.length}`;
        document.getElementById('shareBox').style.display = 'flex';
        sendResults();
        return;
    }

    const question = quizData[current];
    const image = questionImagePath(question.question_image);
    let html = `
        <div class="quiz-progress">${current + 1} / ${quizData.length}</div>
        <h2 class="quiz-question">${escapeHtml(question.question_text)}</h2>
    `;

    if (image) {
        html += `<img class="quiz-question-image" src="${escapeHtml(image)}" alt="ქვიზის კითხვა">`;
    }

    html += '<div class="quiz-options">';
    question.options.forEach((option, index) => {
        html += `
            <label class="quiz-option">
                <input type="radio" name="option" value="${index}">
                <span>${escapeHtml(option.option_text)}</span>
            </label>
        `;
    });
    html += '</div>';

    quizEl.innerHTML = html;
    nextBtn.style.display = 'none';

    document.querySelectorAll('.quiz-option input').forEach((input) => {
        input.addEventListener('change', () => selectOption(Number(input.value)));
    });
}

function selectOption(selected) {
    if (answeredCurrent) return;
    answeredCurrent = true;

    const question = quizData[current];
    const correct = Number(question.correct);
    answers.push({ question_id: question.question_id, selected });
    if (selected === correct) score++;

    document.querySelectorAll('.quiz-option').forEach((label, index) => {
        label.classList.toggle('quiz-option--selected', index === selected);
        label.querySelector('input').disabled = true;
    });
    document.querySelector('.quiz-next-btn').style.display = 'inline-flex';
}

function nextQuestion() {
    current++;
    loadQuestion();
}

function sendResults() {
    if (resultsSent) return;
    resultsSent = true;

    fetch('/sport_blog/admin/save_quiz_result.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': quizCsrfToken },
        body: JSON.stringify({ quiz_id: quizId, answers })
    }).catch(() => {});
}

function shareOnFacebook() {
    const shareText = `მე გავიარე ქვიზი "${quizTitle}" და მივიღე ${score}/${quizData.length} ქულა.`;
    const facebookShareUrl = 'https://www.facebook.com/sharer/sharer.php?u='
        + encodeURIComponent(window.location.href)
        + '&quote=' + encodeURIComponent(shareText);
    window.open(facebookShareUrl, '_blank', 'noopener,noreferrer');
}

loadQuestion();
</script>
<?php endif; ?>
</body>
</html>
