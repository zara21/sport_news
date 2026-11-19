<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_admin_user();

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
        $status = $_POST['status'] ?? 'UPCOMING';
        if (!in_array($sportType, ['UFC', 'F1'], true) || !in_array($status, ['UPCOMING', 'LIVE SOON', 'LIVE NOW'], true)) throw new RuntimeException('სპორტის ტიპი ან სტატუსი არასწორია.');
        if ($eventTitle === '' || $eventDate === '' || $eventTime === '') throw new RuntimeException('სათაური, თარიღი და დრო აუცილებელია.');
        if ($liveLink !== '' && filter_var($liveLink, FILTER_VALIDATE_URL) === false) throw new RuntimeException('პირდაპირი ეთერის ბმული არასწორია.');

        $imageName = upload_media_file('event_image', __DIR__ . '/../uploads/ufc_f1', ['image/jpeg', 'image/png', 'image/webp'], 5 * 1024 * 1024);
        $imagePath = $imageName ? 'ufc_f1/' . $imageName : '';
        $stmt = $pdo->prepare('INSERT INTO sports_events (sport_type, event_title, event_date, event_time, location, live_link, event_image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$sportType, $eventTitle, $eventDate, $eventTime, $location, $liveLink, $imagePath, $status]);
        $successMessage = 'ივენთი წარმატებით დაემატა.';
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

admin_page_start('UFC / F1 ივენთის დამატება');
?>
<section class="admin-card">
    <div class="admin-card__header"><div><p class="admin-eyebrow">UFC / Formula 1</p><h2>ახალი ივენთი</h2></div><a href="manage_UFC_F1_event.php" class="admin-button admin-button--neutral">ივენთების სია</a></div>
    <?php admin_alert($successMessage); ?><?php admin_alert($errorMessage, 'error'); ?>
    <form method="post" enctype="multipart/form-data" class="admin-form">
        <?php admin_csrf_field(); ?>
        <div class="admin-form-grid"><label>სპორტის ტიპი<select name="sport_type"><option value="UFC">UFC</option><option value="F1">Formula 1</option></select></label><label>სტატუსი<select name="status"><option value="UPCOMING">UPCOMING</option><option value="LIVE SOON">LIVE SOON</option><option value="LIVE NOW">LIVE NOW</option></select></label></div>
        <label>ივენთის დასახელება<input type="text" name="event_title" required></label>
        <div class="admin-form-grid"><label>თარიღი<input type="date" name="event_date" required></label><label>დრო<input type="time" name="event_time" required></label></div>
        <label>ლოკაცია<input type="text" name="location"></label><label>პირდაპირი ეთერის ბმული<input type="url" name="live_link" placeholder="https://"></label><label>სურათი<input type="file" name="event_image" accept="image/jpeg,image/png,image/webp"></label>
        <div class="admin-actions"><button type="submit" class="admin-button admin-button--primary">დამატება</button></div>
    </form>
</section>
<?php admin_page_end(); ?>
