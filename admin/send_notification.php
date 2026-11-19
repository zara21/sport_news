<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_admin_user('notifications.manage');

$serverKey = trim((string)getenv('FCM_SERVER_KEY'));
$successMessage = $errorMessage = '';
$results = [];
$users = $pdo->query('SELECT id, first_name, last_name, email FROM users ORDER BY first_name,last_name LIMIT 500')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? null);
    $title = trim((string)($_POST['title'] ?? ''));
    $body = trim((string)($_POST['body'] ?? ''));
    $link = trim((string)($_POST['link'] ?? ''));
    $userId = (int)($_POST['user_id'] ?? 0);
    if ($title === '' || $body === '') {
        $errorMessage = 'სათაური და ტექსტი აუცილებელია.';
    } else {
        $pdo->prepare('INSERT INTO notifications (user_id,title,body,link) VALUES (?,?,?,?)')->execute([$userId ?: null,$title,$body,$link ?: null]);
        if ($serverKey !== '' && !$userId) {
            $tokens = $pdo->query('SELECT token FROM notification_tokens WHERE token IS NOT NULL LIMIT 500')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tokens as $token) {
                $curl = curl_init('https://fcm.googleapis.com/fcm/send');
                curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Authorization: key='.$serverKey,'Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>12,CURLOPT_POSTFIELDS=>json_encode(['to'=>$token,'notification'=>['title'=>$title,'body'=>$body,'icon'=>site_path('/assets/brand/sportblog-logo.svg')]],JSON_UNESCAPED_UNICODE)]);
                curl_exec($curl); $results[]=['token'=>substr((string)$token,0,14).'...','status'=>(int)curl_getinfo($curl,CURLINFO_HTTP_CODE)]; curl_close($curl);
            }
        }
        $successMessage = $userId ? 'პირადი შეტყობინება გაიგზავნა.' : 'შეტყობინება ყველა მომხმარებლის ცენტრში გამოქვეყნდა.';
    }
}

admin_page_start('შეტყობინების გაგზავნა');
?>
<section class="admin-card admin-card--narrow">
    <div class="admin-card__header"><div><p class="admin-eyebrow">Notification Center</p><h2>ახალი შეტყობინება</h2></div><span class="admin-badge <?= $serverKey === '' ? 'admin-badge--warning' : '' ?>"><?= $serverKey === '' ? 'შიდა ცენტრი' : 'Push აქტიურია' ?></span></div>
    <?php admin_alert($successMessage); ?><?php admin_alert($errorMessage, 'error'); ?>
    <form method="post" class="admin-form">
        <?php admin_csrf_field(); ?>
        <label>მიმღები<select name="user_id"><option value="0">ყველა მომხმარებელი</option><?php foreach($users as $user): ?><option value="<?= (int)$user['id'] ?>"><?= htmlspecialchars(trim($user['first_name'].' '.$user['last_name']).' — '.$user['email'],ENT_QUOTES,'UTF-8') ?></option><?php endforeach; ?></select></label>
        <label>სათაური<input type="text" name="title" maxlength="120" required></label>
        <label>ტექსტი<textarea name="body" rows="5" maxlength="500" required></textarea></label>
        <label>ბმული<input type="text" name="link" placeholder="/sport_blog/news/..."></label>
        <div class="admin-actions"><button type="submit" class="admin-button admin-button--primary">გამოქვეყნება</button></div>
    </form>
    <?php if($results): ?><div class="admin-table-wrap"><table class="admin-table admin-table--compact"><thead><tr><th>Token</th><th>HTTP სტატუსი</th></tr></thead><tbody><?php foreach($results as $result): ?><tr><td><?= htmlspecialchars($result['token']) ?></td><td><?= (int)$result['status'] ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
<?php admin_page_end(); ?>
