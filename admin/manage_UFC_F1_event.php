<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_admin_user();

$successMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event'])) {
    verify_csrf_token($_POST['csrf_token'] ?? null);
    $eventId = (int) ($_POST['event_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT event_image FROM sports_events WHERE id = ?');
    $stmt->execute([$eventId]);
    $image = $stmt->fetchColumn();
    $delete = $pdo->prepare('DELETE FROM sports_events WHERE id = ?');
    $delete->execute([$eventId]);
    if ($delete->rowCount() && is_string($image) && str_starts_with($image, 'ufc_f1/')) {
        delete_local_media(substr($image, 7), __DIR__ . '/../uploads/ufc_f1');
    }
    $successMessage = 'ივენთი წაიშალა.';
}

$events = $pdo->query('SELECT id, sport_type, event_title, event_date, event_time, location, status, event_image FROM sports_events ORDER BY event_date ASC, event_time ASC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
admin_page_start('UFC / F1 ივენთები');
?>
<section class="admin-card">
    <div class="admin-card__header">
        <div><p class="admin-eyebrow">კალენდარი</p><h2>სპორტული ივენთები</h2></div>
        <a href="add_UFC_F1_event.php" class="admin-button admin-button--primary">ახალი ივენთი</a>
    </div>
    <?php admin_alert($successMessage); ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>სურათი</th><th>სპორტი</th><th>დასახელება</th><th>თარიღი</th><th>სტატუსი</th><th>მოქმედება</th></tr></thead>
            <tbody>
            <?php foreach ($events as $event): ?>
                <?php $imageUrl = $event['event_image'] ? '/sport_blog/uploads/' . ltrim($event['event_image'], '/') : '/sport_blog/uploads/defaults/article-default.svg'; ?>
                <tr>
                    <td><img src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" class="admin-table-thumb" loading="lazy"></td>
                    <td><span class="admin-badge"><?= htmlspecialchars($event['sport_type'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td><strong><?= htmlspecialchars($event['event_title'], ENT_QUOTES, 'UTF-8') ?></strong><br><small class="admin-muted"><?= htmlspecialchars($event['location'] ?: '—', ENT_QUOTES, 'UTF-8') ?></small></td>
                    <td><?= htmlspecialchars($event['event_date'] . ' ' . substr((string) $event['event_time'], 0, 5), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($event['status'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <div class="admin-actions">
                            <a href="edit_UFC_F1_event.php?id=<?= (int) $event['id'] ?>" class="admin-button admin-button--neutral">რედაქტირება</a>
                            <form method="post" class="admin-inline-form" onsubmit="return confirm('ნამდვილად წავშალოთ ივენთი?')">
                                <?php admin_csrf_field(); ?>
                                <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
                                <button type="submit" name="delete_event" class="admin-button admin-button--danger">წაშლა</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php admin_page_end(); ?>
