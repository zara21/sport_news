<?php
require_once '../includes/db_connect.php';
require_once '../includes/admin_helpers.php';
require_admin_user();
require_once '../includes/header.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? null);
    $title = $_POST['title'];
    $slug = unique_slug($pdo, 'quiz_games', safe_slug(trim((string) ($_POST['slug'] ?? '')) ?: (string) $title, 'quiz'));
    $stat_type = $_POST['stat_type'];
    $duration = (int)$_POST['duration'];

    // ფაილის ტიპის შემოწმება, ზომის ლიმიტი და hash-ით დედუპლიკაცია
    $img_name = upload_media_file('image', __DIR__ . '/../uploads', ['image/jpeg', 'image/png', 'image/webp'], 5 * 1024 * 1024) ?? '';

    $stmt = $pdo->prepare("INSERT INTO quiz_games (title, slug, image, stat_type, duration_minutes) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$title, $slug, $img_name, $stat_type, $duration]);
    $quiz_id = $pdo->lastInsertId();

    foreach ($_POST['players'] as $player) {
        $stmt = $pdo->prepare("INSERT INTO quiz_game_questions (quiz_game_id, player_name_geo, player_name_eng, stat_value) VALUES (?, ?, ?, ?)");
        $stmt->execute([$quiz_id, $player['geo'], $player['eng'], $player['stat']]);
    }

    header("Location: ../quizzes/{$slug}");
    exit;
}
?>

<link rel="stylesheet" href="../css/styles.css">
<link rel="stylesheet" href="../css/add_article.css">

<!-- ფორმა -->

<div class="container">
    <form method="POST" enctype="multipart/form-data">
        <?php admin_csrf_field(); ?>
        <input name="title" placeholder="ქვიზის სახელი" required><br>
        <input name="slug" placeholder="Slug (მაგ. real-madrid-2018)" required><br>
        <input type="file" name="image"><br>
        <select name="stat_type" required>
            <option value="goals">გოლები</option>
            <option value="assists">ასისტები</option>
            <option value="appearances">თამაშები</option>
            <option value="titles">ტიტულები</option>
            <option value="minutes">ნათამაშები წუთები</option>
        </select><br>
        <input type="number" name="duration" placeholder="დრო წუთებში" required><br><br>

        <h3>მოთამაშეები</h3>
        <div id="players-container">
            <div>
                <input name="players[0][geo]" placeholder="სახელი ქართულად" required>
                <input name="players[0][eng]" placeholder="სახელი ინგლისურად" required>
                <input name="players[0][stat]" type="number" placeholder="სტატისტიკა" required>
            </div>
        </div>
        <button type="button" onclick="addPlayer()">+ დამატება</button><br><br>
        <button type="submit">შექმნა</button>
    </form>


</div>



<script>
let count = 1;
function addPlayer() {
    const container = document.getElementById('players-container');
    const div = document.createElement('div');
    div.innerHTML = `
        <input name="players[${count}][geo]" placeholder="სახელი ქართულად" required>
        <input name="players[${count}][eng]" placeholder="სახელი ინგლისურად" required>
        <input name="players[${count}][stat]" type="number" placeholder="სტატისტიკა" required>
    `;
    container.appendChild(div);
    count++;
}
</script>
