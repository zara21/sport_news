<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_once __DIR__ . '/../includes/push.php';
require_admin_user('notifications.manage');

$successMessage = $errorMessage = '';
$pushResult = null;
$pushServer = push_server_enabled();
$pushClient = push_client_enabled();
$users = $pdo->query('SELECT id, first_name, last_name, email FROM users ORDER BY first_name, last_name LIMIT 500')->fetchAll();
$subscribers = (int) $pdo->query('SELECT COUNT(*) FROM notification_tokens')->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? null);
    $title = trim((string) ($_POST['title'] ?? ''));
    $body = trim((string) ($_POST['body'] ?? ''));
    $link = trim((string) ($_POST['link'] ?? ''));
    $userId = (int) ($_POST['user_id'] ?? 0);
    $sendPush = isset($_POST['send_push']);

    if ($title === '' || $body === '') {
        $errorMessage = 'სათაური და ტექსტი აუცილებელია.';
    } elseif ($link !== '' && (!str_starts_with($link, '/sport_blog/') || preg_match('#[\s\\\\]|//#', substr($link, 1)))) {
        $errorMessage = 'ბმული უნდა იყოს საიტის შიდა მისამართი, მაგ. /sport_blog/news/...';
    } else {
        $pdo->prepare('INSERT INTO notifications (user_id, title, body, link) VALUES (?, ?, ?, ?)')
            ->execute([$userId ?: null, $title, $body, $link ?: null]);
        $successMessage = $userId ? 'პირადი შეტყობინება დაემატა.' : 'შეტყობინება ყველა მომხმარებლის ცენტრში გამოქვეყნდა.';

        if ($sendPush && $pushServer) {
            try {
                $pushResult = push_send($pdo, $title, $body, $link, $userId ?: null);
                $successMessage .= " Push: გაიგზავნა {$pushResult['sent']}, ვერ გაიგზავნა {$pushResult['failed']}, წაიშალა გაუქმებული {$pushResult['removed']}.";
            } catch (Throwable $e) {
                error_log('push_send: ' . $e->getMessage());
                $errorMessage = 'შეტყობინება შეინახა, მაგრამ push ვერ გაიგზავნა: ' . $e->getMessage();
            }
        }
    }
}

$h = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
admin_page_start('შეტყობინების გაგზავნა');
?>
<section class="admin-card admin-card--narrow">
    <div class="admin-card__header">
        <div><p class="admin-eyebrow">Notification Center</p><h2>ახალი შეტყობინება</h2></div>
        <span class="admin-badge <?= $pushServer && $pushClient ? '' : 'admin-badge--warning' ?>">
            <?= $pushServer && $pushClient ? 'Push აქტიურია · ' . $subscribers . ' მოწყობილობა' : 'Push გამორთულია' ?>
        </span>
    </div>
    <?php admin_alert($successMessage); ?>
    <?php admin_alert($errorMessage, 'error'); ?>
    <?php if (!$pushServer || !$pushClient): ?>
        <p class="admin-muted">Push-შეტყობინებების ჩასართავად შეავსეთ <code>config/firebase.php</code> (ნიმუში: <code>config/firebase.example.php</code>) და შეინახეთ Firebase service account-ის JSON. მანამდე შეტყობინებები მხოლოდ საიტის შეტყობინებების ცენტრში ჩანს.</p>
    <?php endif; ?>
    <form method="post" class="admin-form">
        <?php admin_csrf_field(); ?>
        <label>მიმღები<select name="user_id"><option value="0">ყველა მომხმარებელი</option><?php foreach ($users as $user): ?><option value="<?= (int) $user['id'] ?>"><?= $h(trim($user['first_name'] . ' ' . $user['last_name']) . ' — ' . $user['email']) ?></option><?php endforeach; ?></select></label>
        <label>სათაური<input type="text" name="title" maxlength="120" required></label>
        <label>ტექსტი<textarea name="body" rows="5" maxlength="500" required></textarea></label>
        <label>ბმული<input type="text" name="link" placeholder="/sport_blog/news/..."></label>
        <label class="admin-checkbox"><input type="checkbox" name="send_push" value="1" <?= $pushServer ? 'checked' : 'disabled' ?>> Push-შეტყობინებაც გაიგზავნოს მოწყობილობებზე</label>
        <div class="admin-actions"><button type="submit" class="admin-button admin-button--primary">გამოქვეყნება</button></div>
    </form>
</section>
<?php admin_page_end(); ?>
