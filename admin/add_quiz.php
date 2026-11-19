<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_admin_user();

$errors = [];
$imageMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? null);

    try {
        $quizTitle = trim($_POST['quiz_title'] ?? '');
        $quizSlug = unique_slug($pdo, 'quizzes', safe_slug(trim($_POST['quiz_slug'] ?? '') ?: $quizTitle, 'quiz'));
        $questions = $_POST['questions'] ?? [];
        $allOptions = $_POST['options'] ?? [];
        $correctAnswers = $_POST['correct'] ?? [];

        if ($quizTitle === '') {
            throw new RuntimeException('შეავსეთ ქვიზის სათაური.');
        }

        if (empty($questions)) {
            throw new RuntimeException('დაამატეთ მინიმუმ ერთი კითხვა.');
        }

        $quizImage = upload_media_file('quiz_image', __DIR__ . '/../uploads/quizzes', $imageMimes, 5 * 1024 * 1024);

        $stmt = $pdo->prepare("INSERT INTO quizzes (title, slug, quiz_image) VALUES (?, ?, ?)");
        $stmt->execute([$quizTitle, $quizSlug, $quizImage]);
        $quizId = (int)$pdo->lastInsertId();

        $stmtQuestion = $pdo->prepare("INSERT INTO questions (quiz_id, question_text, correct_option) VALUES (?, ?, ?)");
        $stmtOption = $pdo->prepare("INSERT INTO options (question_id, option_text) VALUES (?, ?)");

        foreach ($questions as $index => $questionText) {
            $questionText = trim((string)$questionText);
            $options = array_values(array_filter(array_map('trim', $allOptions[$index] ?? [])));
            $correctIndex = isset($correctAnswers[$index]) ? (int)$correctAnswers[$index] : -1;

            if ($questionText === '' || count($options) < 2 || !isset($options[$correctIndex])) {
                continue;
            }

            $stmtQuestion->execute([$quizId, $questionText, $correctIndex]);
            $questionId = (int)$pdo->lastInsertId();

            foreach ($options as $option) {
                $stmtOption->execute([$questionId, $option]);
            }
        }

        header("Location: ../quizzes/{$quizSlug}");
        exit;
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

admin_page_start('ქვიზის დამატება');
?>

<section class="admin-card">
    <div class="admin-card__header">
        <h2>ახალი ქვიზი</h2>
        <a href="quizzes.php" class="admin-button admin-button--neutral">ქვიზები</a>
    </div>

    <?php foreach ($errors as $error): ?>
        <?php admin_alert($error, 'error'); ?>
    <?php endforeach; ?>

    <form class="admin-form" method="POST" enctype="multipart/form-data">
        <?php admin_csrf_field(); ?>
        <label>ქვიზის სათაური
            <input type="text" name="quiz_title" required>
        </label>
        <label>Slug
            <input type="text" name="quiz_slug" placeholder="magari-qvizi">
        </label>
        <label>ქვიზის სურათი
            <input type="file" name="quiz_image" accept="image/*" required>
        </label>

        <div id="questions-wrapper"></div>

        <div class="admin-actions">
            <button type="button" class="admin-button admin-button--neutral" onclick="addQuestion()">კითხვის დამატება</button>
            <button type="submit" class="admin-button admin-button--primary">შენახვა</button>
        </div>
    </form>
</section>

<script>
let qIndex = 0;
function addQuestion() {
    const wrapper = document.getElementById('questions-wrapper');
    const div = document.createElement('div');
    div.className = 'admin-card';
    div.innerHTML = `
        <label>კითხვა
            <input type="text" name="questions[]" required>
        </label>
        <label>პასუხები</label>
        ${[0,1,2,3].map(i => `
            <label class="admin-option-row">
                <input type="radio" name="correct[${qIndex}]" value="${i}" required>
                <input type="text" name="options[${qIndex}][]" required>
            </label>
        `).join('')}
    `;
    wrapper.appendChild(div);
    qIndex++;
}
window.addEventListener('DOMContentLoaded', addQuestion);
</script>

<?php admin_page_end(); ?>
