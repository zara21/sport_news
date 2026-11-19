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

        if (!is_array($questions) || !$questions) {
            throw new RuntimeException('დაამატეთ მინიმუმ ერთი კითხვა.');
        }

        // ვალიდაცია ჩაწერამდე. ცარიელი ვარიანტების ამოღებისას სწორი პასუხის ინდექსი ხელახლა ითვლება
        // (ადრე ინდექსი ძველი რჩებოდა და სწორ პასუხად სხვა ვარიანტი ინახებოდა)
        $prepared = [];
        foreach ($questions as $index => $questionText) {
            $questionText = trim((string) $questionText);
            $rawOptions = is_array($allOptions[$index] ?? null) ? $allOptions[$index] : [];
            $correctOriginal = isset($correctAnswers[$index]) ? (string) $correctAnswers[$index] : '';
            $options = [];
            $correctIndex = -1;
            foreach ($rawOptions as $optionIndex => $optionText) {
                $optionText = trim((string) $optionText);
                if ($optionText === '') continue;
                if ((string) $optionIndex === $correctOriginal) $correctIndex = count($options);
                $options[] = $optionText;
            }
            if ($questionText === '' && !$options) continue; // სრულად ცარიელი ბლოკი
            $number = count($prepared) + 1;
            if ($questionText === '' || count($options) < 2) {
                throw new RuntimeException("კითხვა #{$number}: საჭიროა ტექსტი და მინიმუმ 2 ვარიანტი.");
            }
            if ($correctIndex < 0) {
                throw new RuntimeException("კითხვა #{$number}: მონიშნეთ სწორი პასუხი (არაცარიელი ვარიანტი).");
            }
            $prepared[] = [$questionText, $options, $correctIndex];
        }
        if (!$prepared) {
            throw new RuntimeException('დაამატეთ მინიმუმ ერთი კითხვა.');
        }

        $quizImage = upload_media_file('quiz_image', __DIR__ . '/../uploads/quizzes', $imageMimes, 5 * 1024 * 1024);

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO quizzes (title, slug, quiz_image) VALUES (?, ?, ?)");
        $stmt->execute([$quizTitle, $quizSlug, $quizImage]);
        $quizId = (int)$pdo->lastInsertId();

        $stmtQuestion = $pdo->prepare("INSERT INTO questions (quiz_id, question_text, correct_option) VALUES (?, ?, ?)");
        $stmtOption = $pdo->prepare("INSERT INTO options (question_id, option_text) VALUES (?, ?)");
        foreach ($prepared as [$questionText, $options, $correctIndex]) {
            $stmtQuestion->execute([$quizId, $questionText, $correctIndex]);
            $questionId = (int)$pdo->lastInsertId();
            foreach ($options as $option) {
                $stmtOption->execute([$questionId, $option]);
            }
        }
        $pdo->commit();

        header("Location: ../quizzes/{$quizSlug}");
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (!empty($quizImage)) delete_local_media($quizImage, __DIR__ . '/../uploads/quizzes');
        $errors[] = $e instanceof RuntimeException ? $e->getMessage() : 'ქვიზი ვერ შეინახა.';
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
            <input type="text" name="questions[${qIndex}]" required>
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
