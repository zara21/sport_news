<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_admin_user();

$quizGameId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
if ($quizGameId <= 0) {
    http_response_code(400);
    exit('არასწორი ქვიზის ID.');
}

$successMessage = '';
$errorMessage = '';
$statLabels = ['goals' => 'გოლები', 'assists' => 'ასისტები', 'appearances' => 'მონაწილეობები', 'titles' => 'ტიტულები', 'minutes' => 'წუთები'];

$stmt = $pdo->prepare('SELECT id, title, slug, image, stat_type, duration_minutes FROM quiz_games WHERE id = ?');
$stmt->execute([$quizGameId]);
$quizGame = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$quizGame) {
    http_response_code(404);
    exit('ქვიზი ვერ მოიძებნა.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? null);
    try {
        if (isset($_POST['update_quiz_game'])) {
            $title = trim($_POST['title'] ?? '');
            $statType = $_POST['stat_type'] ?? '';
            $duration = max(1, min(180, (int) ($_POST['duration_minutes'] ?? 10)));
            if ($title === '' || !isset($statLabels[$statType])) {
                throw new RuntimeException('შეავსეთ აუცილებელი ველები სწორად.');
            }

            $slug = unique_slug($pdo, 'quiz_games', safe_slug(trim($_POST['slug'] ?? '') ?: $title, 'quiz-' . $quizGameId), $quizGameId);
            $image = $quizGame['image'];
            $newImage = upload_media_file('image', __DIR__ . '/../uploads', ['image/jpeg', 'image/png', 'image/webp'], 5 * 1024 * 1024);
            $oldImage = $image;
            if ($newImage) {
                $image = $newImage;
            }

            $update = $pdo->prepare('UPDATE quiz_games SET title = ?, slug = ?, image = ?, stat_type = ?, duration_minutes = ? WHERE id = ?');
            $update->execute([$title, $slug, $image, $statType, $duration, $quizGameId]);
            // ძველი სურათი იშლება მხოლოდ ბაზის წარმატებით განახლების შემდეგ
            if ($newImage && $oldImage !== $newImage) {
                delete_local_media($oldImage, __DIR__ . '/../uploads');
            }
            $quizGame = array_merge($quizGame, compact('title', 'slug', 'image'), ['stat_type' => $statType, 'duration_minutes' => $duration]);
            $successMessage = 'ქვიზის ძირითადი ინფორმაცია განახლდა.';
        }

        if (isset($_POST['save_questions'])) {
            $ids = $_POST['question_id'] ?? [];
            $geoNames = $_POST['player_name_geo'] ?? [];
            $engNames = $_POST['player_name_eng'] ?? [];
            $values = $_POST['stat_value'] ?? [];
            $pdo->beginTransaction();

            foreach ($ids as $index => $questionId) {
                $geo = trim($geoNames[$index] ?? '');
                $eng = trim($engNames[$index] ?? '');
                $value = max(0, (int) ($values[$index] ?? 0));
                if ($questionId === 'new') {
                    if ($geo !== '' && $eng !== '') {
                        $insert = $pdo->prepare('INSERT INTO quiz_game_questions (quiz_game_id, player_name_geo, player_name_eng, stat_value) VALUES (?, ?, ?, ?)');
                        $insert->execute([$quizGameId, $geo, $eng, $value]);
                    }
                } elseif ($geo === '' && $eng === '') {
                    $delete = $pdo->prepare('DELETE FROM quiz_game_questions WHERE id = ? AND quiz_game_id = ?');
                    $delete->execute([(int) $questionId, $quizGameId]);
                } else {
                    $update = $pdo->prepare('UPDATE quiz_game_questions SET player_name_geo = ?, player_name_eng = ?, stat_value = ? WHERE id = ? AND quiz_game_id = ?');
                    $update->execute([$geo, $eng, $value, (int) $questionId, $quizGameId]);
                }
            }
            $pdo->commit();
            $successMessage = 'ქვიზის კითხვები განახლდა.';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errorMessage = $e->getMessage();
    }
}

$stmt = $pdo->prepare('SELECT id, player_name_geo, player_name_eng, stat_value FROM quiz_game_questions WHERE quiz_game_id = ? ORDER BY id');
$stmt->execute([$quizGameId]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
$imageUrl = !empty($quizGame['image']) ? '/sport_blog/uploads/' . ltrim($quizGame['image'], '/') : '/sport_blog/uploads/defaults/article-default.svg';

admin_page_start('სტატისტიკური ქვიზის რედაქტირება');
?>
<?php admin_alert($successMessage); ?>
<?php admin_alert($errorMessage, 'error'); ?>

<div class="admin-dashboard-grid">
    <section class="admin-card">
        <div class="admin-card__header"><div><p class="admin-eyebrow">ქვიზი #<?= $quizGameId ?></p><h2>ძირითადი ინფორმაცია</h2></div><a href="quiz_games.php" class="admin-button admin-button--neutral">ქვიზების სია</a></div>
        <form method="post" enctype="multipart/form-data" class="admin-form">
            <?php admin_csrf_field(); ?>
            <label>სათაური<input type="text" name="title" value="<?= htmlspecialchars($quizGame['title'], ENT_QUOTES, 'UTF-8') ?>" required></label>
            <label>Slug<input type="text" name="slug" value="<?= htmlspecialchars($quizGame['slug'], ENT_QUOTES, 'UTF-8') ?>" required></label>
            <div class="admin-form-grid">
                <label>სტატისტიკის ტიპი<select name="stat_type" required><?php foreach ($statLabels as $value => $label): ?><option value="<?= $value ?>" <?= $quizGame['stat_type'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
                <label>ხანგრძლივობა (წუთი)<input type="number" name="duration_minutes" min="1" max="180" value="<?= (int) $quizGame['duration_minutes'] ?>" required></label>
            </div>
            <label>ახალი სურათი<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
            <div class="admin-actions"><button type="submit" name="update_quiz_game" class="admin-button admin-button--primary">განახლება</button></div>
        </form>
    </section>
    <aside class="admin-card"><p class="admin-eyebrow">Preview</p><img src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" class="admin-event-preview"><h3><?= htmlspecialchars($quizGame['title'], ENT_QUOTES, 'UTF-8') ?></h3><p class="admin-muted"><?= htmlspecialchars($statLabels[$quizGame['stat_type']] ?? $quizGame['stat_type'], ENT_QUOTES, 'UTF-8') ?> · <?= (int) $quizGame['duration_minutes'] ?> წუთი</p></aside>
</div>

<section class="admin-card">
    <div class="admin-card__header"><div><p class="admin-eyebrow">კითხვები</p><h2>მოთამაშეები და მნიშვნელობები</h2></div></div>
    <p class="admin-muted">ორივე სახელის გასუფთავება ჩანაწერს წაშლის. ქვედა ცარიელი მწკრივი ახალ ჩანაწერს ამატებს.</p>
    <form method="post" class="admin-form">
        <?php admin_csrf_field(); ?>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>ID</th><th>სახელი ქართულად</th><th>სახელი ინგლისურად</th><th>მნიშვნელობა</th></tr></thead><tbody>
        <?php foreach ($questions as $question): ?><tr><td><input type="hidden" name="question_id[]" value="<?= (int) $question['id'] ?>"><?= (int) $question['id'] ?></td><td><input type="text" name="player_name_geo[]" value="<?= htmlspecialchars($question['player_name_geo'], ENT_QUOTES, 'UTF-8') ?>"></td><td><input type="text" name="player_name_eng[]" value="<?= htmlspecialchars($question['player_name_eng'], ENT_QUOTES, 'UTF-8') ?>"></td><td><input type="number" name="stat_value[]" min="0" value="<?= (int) $question['stat_value'] ?>"></td></tr><?php endforeach; ?>
        <tr><td><input type="hidden" name="question_id[]" value="new">ახალი</td><td><input type="text" name="player_name_geo[]" placeholder="ქართული სახელი"></td><td><input type="text" name="player_name_eng[]" placeholder="English name"></td><td><input type="number" name="stat_value[]" min="0" value="0"></td></tr>
        </tbody></table></div>
        <div class="admin-actions"><button type="submit" name="save_questions" class="admin-button admin-button--primary">კითხვების შენახვა</button></div>
    </form>
</section>
<?php admin_page_end(); ?>
