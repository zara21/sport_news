<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/admin_layout.php';

require_admin_user();

$quizId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($quizId <= 0) {
    http_response_code(400);
    exit('არასწორი ქვიზის ID.');
}

function quiz_image_url(?string $image): string
{
    $image = trim((string)$image);
    if ($image === '') {
        return '/sport_blog/uploads/defaults/article-default.svg';
    }
    if (preg_match('/^https?:\/\//i', $image) || str_starts_with($image, '/')) {
        return $image;
    }

    $image = preg_replace('#^\.\./#', '', $image);
    if (str_starts_with($image, 'uploads/')) {
        return '/sport_blog/' . $image;
    }

    return '/sport_blog/uploads/quizzes/' . basename($image);
}

function quiz_image_name(?string $image): string
{
    $image = trim((string)$image);
    if ($image === '' || preg_match('/^https?:\/\//i', $image)) {
        return '';
    }

    return basename($image);
}

function upload_quiz_image_from_file(array $file): ?string
{
    if (empty($file['name'])) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('სურათის ატვირთვა ვერ მოხერხდა.');
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('სურათი 5MB-ზე დიდი არ უნდა იყოს.');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('სურათის ტიპი არ არის დაშვებული.');
    }

    $dir = __DIR__ . '/../uploads/quizzes';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $filename = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    $destination = $dir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('სურათის შენახვა ვერ მოხერხდა.');
    }

    if (function_exists('optimize_uploaded_image')) {
        optimize_uploaded_image($destination, $mime);
    }

    return $filename;
}

function delete_quiz_image(?string $image): void
{
    $name = quiz_image_name($image);
    if ($name === '') {
        return;
    }

    $path = realpath(__DIR__ . '/../uploads/quizzes/' . $name);
    $base = realpath(__DIR__ . '/../uploads/quizzes');
    if ($path && $base && strpos($path, $base) === 0 && is_file($path)) {
        unlink($path);
    }
}

$stmt = $pdo->prepare('SELECT * FROM quizzes WHERE id = ?');
$stmt->execute([$quizId]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$quiz) {
    http_response_code(404);
    exit('ქვიზი ვერ მოიძებნა.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? null);

    try {
        if (isset($_POST['update_quiz'])) {
            $title = trim((string)($_POST['title'] ?? ''));
            $slugInput = trim((string)($_POST['slug'] ?? ''));
            $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;

            if ($title === '') {
                throw new RuntimeException('ქვიზის სათაური სავალდებულოა.');
            }

            $slug = unique_slug($pdo, 'quizzes', safe_slug($slugInput !== '' ? $slugInput : $title, 'quiz'), $quizId);
            $quizImage = $quiz['quiz_image'];
            $oldCover = $quizImage;
            $newImage = upload_quiz_image_from_file($_FILES['quiz_cover'] ?? []);
            if ($newImage) {
                $quizImage = $newImage;
            }

            $update = $pdo->prepare('UPDATE quizzes SET title = ?, slug = ?, category_id = ?, quiz_image = ? WHERE id = ?');
            $update->execute([$title, $slug, $categoryId, $quizImage, $quizId]);
            if ($newImage && $oldCover && !media_is_referenced($pdo, quiz_image_name($oldCover))) {
                delete_quiz_image($oldCover);
            }
            $_SESSION['success_message'] = 'ქვიზის ძირითადი ინფორმაცია განახლდა.';
        }

        if (isset($_POST['save_questions'])) {
            $ids = is_array($_POST['question_id'] ?? null) ? $_POST['question_id'] : [];
            $texts = is_array($_POST['question_text'] ?? null) ? $_POST['question_text'] : [];
            $correctOptions = is_array($_POST['correct_option'] ?? null) ? $_POST['correct_option'] : [];
            $difficulties = is_array($_POST['difficulty'] ?? null) ? $_POST['difficulty'] : [];
            $optionsTexts = is_array($_POST['option_text'] ?? null) ? $_POST['option_text'] : [];

            // არსებული სურათები ბაზიდან (არა ფორმის დამალული ველიდან — მას ვერ ვენდობით)
            $existingStmt = $pdo->prepare('SELECT id, quiz_image FROM questions WHERE quiz_id = ?');
            $existingStmt->execute([$quizId]);
            $existingImages = $existingStmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // 1) ვალიდაცია და მომზადება
            $prepared = [];
            $number = 0;
            foreach ($ids as $index => $rawId) {
                $isNew = $rawId === 'new';
                $questionId = $isNew ? 0 : (int) $rawId;
                if (!$isNew && !array_key_exists($questionId, $existingImages)) {
                    continue; // სხვა ქვიზის ან არარსებული კითხვა
                }
                $text = trim((string) ($texts[$index] ?? ''));
                $difficulty = in_array(($difficulties[$index] ?? 'medium'), ['easy', 'medium', 'hard'], true) ? $difficulties[$index] : 'medium';

                if ($text === '') {
                    if (!$isNew) $prepared[] = ['delete', $questionId, $index];
                    continue;
                }
                $number++;

                // ცარიელი ვარიანტების ამოღება + სწორი პასუხის ინდექსის გადათვლა
                $rawOptions = is_array($optionsTexts[$index] ?? null) ? array_values($optionsTexts[$index]) : [];
                $correctRaw = (int) ($correctOptions[$index] ?? -1);
                $options = [];
                $correctIndex = -1;
                foreach ($rawOptions as $optionIndex => $optionText) {
                    $optionText = trim((string) $optionText);
                    if ($optionText === '') continue;
                    if ($optionIndex === $correctRaw) $correctIndex = count($options);
                    $options[] = $optionText;
                }
                if (count($options) < 2) {
                    throw new RuntimeException("კითხვა #{$number}: საჭიროა მინიმუმ 2 ვარიანტი.");
                }
                if ($correctIndex < 0) {
                    throw new RuntimeException("კითხვა #{$number}: სწორი პასუხის ნომერი არ შეესაბამება არაცარიელ ვარიანტს.");
                }
                $prepared[] = [$isNew ? 'insert' : 'update', $questionId, $index, $text, $correctIndex, $difficulty, $options];
            }

            // 2) სურათების ატვირთვა (ახალი ფაილები შეცდომისას წაიშლება)
            $newUploads = [];
            $replacedImages = [];
            $imagesByIndex = [];
            foreach ($prepared as $item) {
                [$action, $questionId, $index] = $item;
                $current = $questionId ? (string) ($existingImages[$questionId] ?? '') : '';
                if ($action === 'delete') {
                    $replacedImages[] = $current;
                    continue;
                }
                $imagesByIndex[$index] = $current;
                if (!empty($_FILES['question_image']['name'][$index])) {
                    $uploaded = upload_quiz_image_from_file([
                        'name' => $_FILES['question_image']['name'][$index],
                        'tmp_name' => $_FILES['question_image']['tmp_name'][$index],
                        'type' => $_FILES['question_image']['type'][$index] ?? '',
                        'error' => $_FILES['question_image']['error'][$index],
                        'size' => $_FILES['question_image']['size'][$index],
                    ]);
                    if ($uploaded) {
                        $newUploads[] = $uploaded;
                        $replacedImages[] = $current;
                        $imagesByIndex[$index] = $uploaded;
                    }
                }
            }

            // 3) ჩაწერა ერთ transaction-ში
            $pdo->beginTransaction();
            try {
                $insertQuestion = $pdo->prepare('INSERT INTO questions (quiz_id, question_text, correct_option, difficulty, quiz_image) VALUES (?, ?, ?, ?, ?)');
                $updateQuestion = $pdo->prepare('UPDATE questions SET question_text = ?, correct_option = ?, difficulty = ?, quiz_image = ? WHERE id = ? AND quiz_id = ?');
                $deleteOptions = $pdo->prepare('DELETE FROM options WHERE question_id = ?');
                $deleteQuestion = $pdo->prepare('DELETE FROM questions WHERE id = ? AND quiz_id = ?');
                $insertOption = $pdo->prepare('INSERT INTO options (question_id, option_text) VALUES (?, ?)');

                foreach ($prepared as $item) {
                    if ($item[0] === 'delete') {
                        $deleteOptions->execute([$item[1]]);
                        $deleteQuestion->execute([$item[1], $quizId]);
                        continue;
                    }
                    [$action, $questionId, $index, $text, $correctIndex, $difficulty, $options] = $item;
                    $image = $imagesByIndex[$index] ?? '';
                    if ($action === 'insert') {
                        $insertQuestion->execute([$quizId, $text, $correctIndex, $difficulty, $image]);
                        $questionId = (int) $pdo->lastInsertId();
                    } else {
                        $updateQuestion->execute([$text, $correctIndex, $difficulty, $image, $questionId, $quizId]);
                        $deleteOptions->execute([$questionId]);
                    }
                    foreach ($options as $option) {
                        $insertOption->execute([$questionId, $option]);
                    }
                }
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                foreach ($newUploads as $file) delete_quiz_image($file);
                throw $e;
            }

            // ძველი სურათები იშლება მხოლოდ წარმატებული შენახვის შემდეგ და მხოლოდ თუ აღარ გამოიყენება
            foreach (array_filter($replacedImages) as $oldImage) {
                if (!media_is_referenced($pdo, quiz_image_name($oldImage))) delete_quiz_image($oldImage);
            }

            $_SESSION['success_message'] = 'ქვიზის კითხვები და პასუხები განახლდა.';
        }

        header('Location: edit_quiz.php?id=' . $quizId);
        exit;
    } catch (Throwable $e) {
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: edit_quiz.php?id=' . $quizId);
        exit;
    }
}

$stmt = $pdo->prepare('SELECT * FROM questions WHERE quiz_id = ? ORDER BY id ASC');
$stmt->execute([$quizId]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$optionsByQuestion = [];
if ($questions) {
    $questionIds = array_column($questions, 'id');
    $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
    $stmt = $pdo->prepare("SELECT * FROM options WHERE question_id IN ($placeholders) ORDER BY question_id, id");
    $stmt->execute($questionIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $option) {
        $optionsByQuestion[$option['question_id']][] = $option;
    }
}

admin_page_start('ქვიზის რედაქტირება', ['/sport_blog/css/add_article.css?v=20261119g']);
?>

<section class="admin-editor">
    <div class="admin-editor__header">
        <div>
            <p class="admin-eyebrow">ქვიზები</p>
            <h2>ქვიზის რედაქტირება</h2>
        </div>
        <div class="admin-actions">
            <a class="admin-button admin-button--neutral" href="quizzes.php">ქვიზები</a>
            <a class="admin-button admin-button--neutral" href="/sport_blog/quizzes/<?= htmlspecialchars($quiz['slug'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">ნახვა</a>
        </div>
    </div>

    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="admin-alert admin-alert--success"><?= htmlspecialchars($_SESSION['success_message'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error_message'])): ?>
        <div class="admin-alert admin-alert--error"><?= htmlspecialchars($_SESSION['error_message'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <form class="article-form" method="post" enctype="multipart/form-data">
        <?php admin_csrf_field(); ?>
        <section class="form-section form-grid">
            <label>სათაური
                <input type="text" name="title" value="<?= htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8') ?>" required>
            </label>
            <label>Slug
                <input type="text" name="slug" value="<?= htmlspecialchars($quiz['slug'], ENT_QUOTES, 'UTF-8') ?>" required>
            </label>
            <label>Category ID
                <input type="number" name="category_id" value="<?= htmlspecialchars((string)($quiz['category_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" min="0">
            </label>
            <label>Cover image
                <input class="media-input" type="file" name="quiz_cover" accept="image/*" data-preview="quiz-cover-preview">
            </label>
            <label class="form-grid__wide">არსებული სურათი
                <img id="quiz-cover-preview" class="media-preview" src="<?= htmlspecialchars(quiz_image_url($quiz['quiz_image'] ?? null), ENT_QUOTES, 'UTF-8') ?>" alt="">
            </label>
        </section>

        <div class="admin-form-actions">
            <button class="admin-button admin-button--primary" type="submit" name="update_quiz">ძირითადი ინფორმაციის შენახვა</button>
        </div>
    </form>

    <form class="article-form" method="post" enctype="multipart/form-data">
        <?php admin_csrf_field(); ?>
        <section class="form-section">
            <h2>კითხვები და პასუხები</h2>
            <p class="admin-muted">ცარიელი არსებული კითხვა წაიშლება. ახალი კითხვა შეინახება მხოლოდ ტექსტის შევსების შემთხვევაში.</p>

            <div class="quiz-question-builder">
                <?php foreach ($questions as $index => $question): ?>
                    <?php $options = $optionsByQuestion[$question['id']] ?? []; ?>
                    <article class="quiz-question-card">
                        <div class="quiz-question-card__header">
                            <strong>კითხვა #<?= (int)$question['id'] ?></strong>
                            <span class="admin-badge"><?= htmlspecialchars($question['difficulty'] ?? 'medium', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <input type="hidden" name="question_id[]" value="<?= (int)$question['id'] ?>">

                        <label>კითხვის ტექსტი
                            <textarea name="question_text[]" rows="3"><?= htmlspecialchars($question['question_text'], ENT_QUOTES, 'UTF-8') ?></textarea>
                        </label>

                        <div class="form-grid">
                            <label>სწორი პასუხის ნომერი
                                <input type="number" name="correct_option[]" min="0" value="<?= (int)$question['correct_option'] ?>">
                            </label>
                            <label>სირთულე
                                <select name="difficulty[]">
                                    <?php foreach (['easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard'] as $value => $label): ?>
                                        <option value="<?= $value ?>" <?= ($question['difficulty'] ?? 'medium') === $value ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>

                        <div class="media-preview-grid">
                            <label>კითხვის სურათი
                                <input class="media-input" type="file" name="question_image[]" accept="image/*" data-preview="question-image-<?= $index ?>">
                            </label>
                            <img id="question-image-<?= $index ?>" class="media-preview" src="<?= htmlspecialchars(quiz_image_url($question['quiz_image'] ?? null), ENT_QUOTES, 'UTF-8') ?>" alt="">
                        </div>

                        <div class="quiz-options-grid">
                            <?php for ($i = 0; $i < max(4, count($options) + 1); $i++): ?>
                                <label>პასუხი #<?= $i + 1 ?>
                                    <input type="text" name="option_text[<?= $index ?>][]" value="<?= htmlspecialchars($options[$i]['option_text'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                </label>
                            <?php endfor; ?>
                        </div>
                    </article>
                <?php endforeach; ?>

                <?php $newIndex = count($questions); ?>
                <article class="quiz-question-card quiz-question-card--new">
                    <div class="quiz-question-card__header">
                        <strong>ახალი კითხვა</strong>
                        <span class="admin-badge">new</span>
                    </div>
                    <input type="hidden" name="question_id[]" value="new">

                    <label>კითხვის ტექსტი
                        <textarea name="question_text[]" rows="3" placeholder="ახალი კითხვის ტექსტი"></textarea>
                    </label>

                    <div class="form-grid">
                        <label>სწორი პასუხის ნომერი
                            <input type="number" name="correct_option[]" min="0" value="0">
                        </label>
                        <label>სირთულე
                            <select name="difficulty[]">
                                <option value="easy">Easy</option>
                                <option value="medium" selected>Medium</option>
                                <option value="hard">Hard</option>
                            </select>
                        </label>
                    </div>

                    <label>კითხვის სურათი
                        <input class="media-input" type="file" name="question_image[]" accept="image/*" data-preview="question-image-new">
                    </label>
                    <img id="question-image-new" class="media-preview" src="/sport_blog/uploads/defaults/article-default.svg" alt="">

                    <div class="quiz-options-grid">
                        <?php for ($i = 0; $i < 4; $i++): ?>
                            <label>პასუხი #<?= $i + 1 ?>
                                <input type="text" name="option_text[<?= $newIndex ?>][]">
                            </label>
                        <?php endfor; ?>
                    </div>
                </article>
            </div>
        </section>

        <div class="admin-form-actions">
            <button class="admin-button admin-button--primary" type="submit" name="save_questions">კითხვების შენახვა</button>
        </div>
    </form>
</section>

<script src="../js/article_editor.js"></script>

<?php admin_page_end(); ?>
