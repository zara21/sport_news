<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_admin_user();

$eventId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$stmt = $pdo->prepare('SELECT id, sport_type, event_title, event_date, event_time, location, live_link, event_image, status FROM sports_events WHERE id = ?');
$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$event) {
    http_response_code(404);
    exit('ივენთი ვერ მოიძებნა.');
}

$successMessage = '';
$errorMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? null);
    try {
        $sportType = $_POST['sport_type'] ?? '';
        $eventTitle = trim($_POST['event_title'] ?? '');
        $eventDate = $_POST['event_date'] ?? '';
        $eventTime = $_POST['event_time'] ?? '';
        $location = trim($_POST['location'] ?? '');
        $liveLink = trim($_POST['live_link'] ?? '');
        $status = $_POST['status'] ?? '';
        if (!in_array($sportType, ['UFC', 'F1'], true) || !in_array($status, ['UPCOMING', 'LIVE SOON', 'LIVE NOW'], true)) throw new RuntimeException('სპორტის ტიპი ან სტატუსი არასწორია.');
        if ($eventTitle === '' || $eventDate === '' || $eventTime === '') throw new RuntimeException('სათაური, თარიღი და დრო აუცილებელია.');
        if ($liveLink !== '' && filter_var($liveLink, FILTER_VALIDATE_URL) === false) throw new RuntimeException('პირდაპირი ეთერის ბმული არასწორია.');

        $imagePath = $event['event_image'];
        $newImage = upload_media_file('event_image', __DIR__ . '/../uploads/ufc_f1', ['image/jpeg', 'image/png', 'image/webp'], 5 * 1024 * 1024);
        $oldImagePath = (string) $imagePath;
        if ($newImage) {
            $imagePath = 'ufc_f1/' . $newImage;
        }

        $update = $pdo->prepare('UPDATE sports_events SET sport_type = ?, event_title = ?, event_date = ?, event_time = ?, location = ?, live_link = ?, event_image = ?, status = ? WHERE id = ?');
        $update->execute([$sportType, $eventTitle, $eventDate, $eventTime, $location, $liveLink, $imagePath, $status, $eventId]);
        // ძველი სურათი იშლება მხოლოდ ბაზის წარმატებით განახლების შემდეგ
        if ($newImage && $oldImagePath !== $imagePath && str_starts_with($oldImagePath, 'ufc_f1/')) {
            delete_local_media(substr($oldImagePath, 7), __DIR__ . '/../uploads/ufc_f1');
        }
        $event = array_merge($event, ['sport_type' => $sportType, 'event_title' => $eventTitle, 'event_date' => $eventDate, 'event_time' => $eventTime, 'location' => $location, 'live_link' => $liveLink, 'event_image' => $imagePath, 'status' => $status]);
        $successMessage = 'ივენთი წარმატებით განახლდა.';
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$imageUrl = $event['event_image'] ? '/sport_blog/uploads/' . ltrim($event['event_image'], '/') : '/sport_blog/uploads/defaults/article-default.svg';
admin_page_start('ივენთის რედაქტირება');
?>
<section class="admin-card">
    <div class="admin-card__header"><div><p class="admin-eyebrow">UFC / F1</p><h2><?= htmlspecialchars($event['event_title'], ENT_QUOTES, 'UTF-8') ?></h2></div><a href="manage_UFC_F1_event.php" class="admin-button admin-button--neutral">ივენთები</a></div>
    <?php admin_alert($successMessage); ?><?php admin_alert($errorMessage, 'error'); ?>
    <form method="post" enctype="multipart/form-data" class="admin-form">
        <?php admin_csrf_field(); ?>
        <div class="admin-event-grid"><div class="admin-event-fields">
            <div class="admin-form-grid"><label>სპორტის ტიპი<select name="sport_type"><option value="UFC" <?= $event['sport_type'] === 'UFC' ? 'selected' : '' ?>>UFC</option><option value="F1" <?= $event['sport_type'] === 'F1' ? 'selected' : '' ?>>Formula 1</option></select></label><label>სტატუსი<select name="status"><?php foreach (['UPCOMING', 'LIVE SOON', 'LIVE NOW'] as $status): ?><option value="<?= $status ?>" <?= $event['status'] === $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select></label></div>
            <label>ივენთის დასახელება<input type="text" name="event_title" value="<?= htmlspecialchars($event['event_title'], ENT_QUOTES, 'UTF-8') ?>" required></label>
            <div class="admin-form-grid"><label>თარიღი<input type="date" name="event_date" value="<?= htmlspecialchars($event['event_date'], ENT_QUOTES, 'UTF-8') ?>" required></label><label>დრო<input type="time" name="event_time" value="<?= htmlspecialchars(substr((string) $event['event_time'], 0, 5), ENT_QUOTES, 'UTF-8') ?>" required></label></div>
            <label>ლოკაცია<input type="text" name="location" value="<?= htmlspecialchars($event['location'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label><label>პირდაპირი ეთერის ბმული<input type="url" name="live_link" value="<?= htmlspecialchars($event['live_link'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label>
        </div><aside class="admin-event-media"><img src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" class="admin-event-preview"><label>ახალი სურათი<input type="file" name="event_image" accept="image/jpeg,image/png,image/webp"></label><p class="admin-muted">ახალი სურათის ატვირთვისას ძველი ლოკალური ფაილი ავტომატურად წაიშლება.</p></aside></div>
        <div class="admin-actions"><button type="submit" class="admin-button admin-button--primary">განახლება</button><a href="manage_UFC_F1_event.php" class="admin-button admin-button--neutral">გაუქმება</a></div>
    </form>
</section>
<?php admin_page_end(); ?>
