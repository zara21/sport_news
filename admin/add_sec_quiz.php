<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_admin_user();

$statLabels = ['goals' => 'გოლები', 'assists' => 'ასისტები', 'appearances' => 'მონაწილეობები', 'titles' => 'ტიტულები', 'minutes' => 'წუთები'];
$errorMessage = '';
$form = ['title' => '', 'slug' => '', 'stat_type' => 'goals', 'duration' => 10, 'players' => [['geo' => '', 'eng' => '', 'stat' => '']]];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? null);

    $form['title'] = trim((string) ($_POST['title'] ?? ''));
    $form['slug'] = trim((string) ($_POST['slug'] ?? ''));
    $form['stat_type'] = (string) ($_POST['stat_type'] ?? '');
    $form['duration'] = max(1, min(180, (int) ($_POST['duration'] ?? 10)));

    $players = [];
    foreach (is_array($_POST['players'] ?? null) ? $_POST['players'] : [] as $row) {
        if (!is_array($row)) continue;
        $geo = trim((string) ($row['geo'] ?? ''));
        $eng = trim((string) ($row['eng'] ?? ''));
        $stat = trim((string) ($row['stat'] ?? ''));
        if ($geo === '' && $eng === '') continue;
        $players[] = ['geo' => $geo, 'eng' => $eng, 'stat' => $stat];
    }
    $form['players'] = $players ?: $form['players'];

    $newImage = null;
    try {
        if ($form['title'] === '' || !isset($statLabels[$form['stat_type']])) {
            throw new RuntimeException('შეავსეთ სათაური და აირჩიეთ სტატისტიკის ტიპი.');
        }
        if (!$players) {
            throw new RuntimeException('დაამატეთ მინიმუმ ერთი მოთამაშე.');
        }
        foreach ($players as $player) {
            if ($player['geo'] === '' || $player['eng'] === '' || !ctype_digit($player['stat'])) {
                throw new RuntimeException('თითოეულ მოთამაშეს სჭირდება ორივე სახელი და არაუარყოფითი რიცხვი.');
            }
        }

        $slug = unique_slug($pdo, 'quiz_games', safe_slug($form['slug'] !== '' ? $form['slug'] : $form['title'], 'quiz'));
        $newImage = upload_media_file('image', __DIR__ . '/../uploads', ['image/jpeg', 'image/png', 'image/webp'], 5 * 1024 * 1024);

        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO quiz_games (title, slug, image, stat_type, duration_minutes) VALUES (?, ?, ?, ?, ?)')
            ->execute([$form['title'], $slug, $newImage ?? '', $form['stat_type'], $form['duration']]);
        $quizGameId = (int) $pdo->lastInsertId();
        $insert = $pdo->prepare('INSERT INTO quiz_game_questions (quiz_game_id, player_name_geo, player_name_eng, stat_value) VALUES (?, ?, ?, ?)');
        foreach ($players as $player) {
            $insert->execute([$quizGameId, $player['geo'], $player['eng'], (int) $player['stat']]);
        }
        $pdo->commit();

        header('Location: edit_quiz_game.php?id=' . $quizGameId, true, 303);
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($newImage) delete_local_media($newImage, __DIR__ . '/../uploads');
        $errorMessage = $e instanceof RuntimeException ? $e->getMessage() : 'ქვიზი ვერ შეინახა.';
        if (!$e instanceof RuntimeException) error_log('add_sec_quiz: ' . $e->getMessage());
    }
}

$h = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
admin_page_start('სტატისტიკური ქვიზის დამატება');
admin_alert($errorMessage, 'error');
?>
<form method="post" enctype="multipart/form-data" class="admin-form">
    <?php admin_csrf_field(); ?>
    <section class="admin-card">
        <div class="admin-card__header">
            <div><p class="admin-eyebrow">ახალი ქვიზი</p><h2>ძირითადი ინფორმაცია</h2></div>
            <a href="quiz_games.php" class="admin-button admin-button--neutral">ქვიზების სია</a>
        </div>
        <label>სათაური<input type="text" name="title" value="<?= $h($form['title']) ?>" maxlength="255" required></label>
        <label>Slug <small class="admin-muted">(ცარიელი = სათაურიდან ავტომატურად)</small><input type="text" name="slug" value="<?= $h($form['slug']) ?>" placeholder="real-madrid-2018"></label>
        <div class="admin-form-grid">
            <label>სტატისტიკის ტიპი<select name="stat_type" required><?php foreach ($statLabels as $value => $label): ?><option value="<?= $value ?>" <?= $form['stat_type'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
            <label>ხანგრძლივობა (წუთი)<input type="number" name="duration" min="1" max="180" value="<?= (int) $form['duration'] ?>" required></label>
        </div>
        <label>სურათი<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
    </section>

    <section class="admin-card">
        <div class="admin-card__header">
            <div><p class="admin-eyebrow">კითხვები</p><h2>მოთამაშეები და მნიშვნელობები</h2></div>
            <button type="button" class="admin-button admin-button--neutral" id="addPlayer">+ მოთამაშე</button>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>სახელი ქართულად</th><th>სახელი ინგლისურად</th><th>მნიშვნელობა</th><th></th></tr></thead>
                <tbody id="playersBody">
                <?php foreach ($form['players'] as $index => $player): ?>
                    <tr>
                        <td><input type="text" name="players[<?= (int) $index ?>][geo]" value="<?= $h($player['geo']) ?>" required></td>
                        <td><input type="text" name="players[<?= (int) $index ?>][eng]" value="<?= $h($player['eng']) ?>" required></td>
                        <td><input type="number" name="players[<?= (int) $index ?>][stat]" min="0" value="<?= $h($player['stat']) ?>" required></td>
                        <td><button type="button" class="admin-button admin-button--danger" data-remove-player>×</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="admin-actions"><button type="submit" class="admin-button admin-button--primary">ქვიზის შექმნა</button></div>
    </section>
</form>

<script>
(() => {
    const body = document.getElementById('playersBody');
    let next = body.rows.length;
    document.getElementById('addPlayer').addEventListener('click', () => {
        const row = body.rows[0].cloneNode(true);
        row.querySelectorAll('input').forEach((input) => {
            input.value = '';
            input.name = input.name.replace(/players\[\d+\]/, `players[${next}]`);
        });
        body.appendChild(row);
        next++;
        row.querySelector('input').focus();
    });
    body.addEventListener('click', (event) => {
        if (!event.target.matches('[data-remove-player]') || body.rows.length === 1) return;
        event.target.closest('tr').remove();
    });
})();
</script>
<?php admin_page_end(); ?>
