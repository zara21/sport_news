<?php
require_once __DIR__ . '/../includes/auth_helpers.php'; auth_start_session();
require_once __DIR__ . '/../includes/db_connect.php';
if (empty($_SESSION['user_id'])) site_redirect('/auth_system/login');
$userId=(int)$_SESSION['user_id'];
if($_SERVER['REQUEST_METHOD']==='POST' && verify_form_csrf_token($_POST['csrf_token']??null)){
    $pdo->prepare('INSERT IGNORE INTO notification_reads (notification_id,user_id) SELECT id,? FROM notifications WHERE user_id IS NULL OR user_id=?')->execute([$userId,$userId]);
    header('Location: '.site_path('/views/notifications')); exit;
}
$stmt=$pdo->prepare('SELECT n.*,nr.read_at FROM notifications n LEFT JOIN notification_reads nr ON nr.notification_id=n.id AND nr.user_id=? WHERE n.user_id IS NULL OR n.user_id=? ORDER BY n.created_at DESC LIMIT 100');
$stmt->execute([$userId,$userId]);$notifications=$stmt->fetchAll();
?>
<!doctype html><html lang="ka"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>შეტყობინებები | Sport Blog</title><link rel="stylesheet" href="/sport_blog/css/styles.css?v=20261119e"><link rel="stylesheet" href="/sport_blog/css/navbar.css?v=20261119e"><link rel="stylesheet" href="/sport_blog/css/profile.css?v=20261119e"></head><body><?php require __DIR__.'/../includes/header.php'; ?><main class="profile-shell"><section class="user-library"><header class="profile-settings__heading"><div><p class="user-profile__eyebrow">ანგარიში</p><h1>შეტყობინებები</h1></div><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(form_csrf_token()) ?>"><button class="user-profile__button user-profile__button--neutral">ყველას წაკითხულად მონიშვნა</button></form></header><div class="library-list"><?php foreach($notifications as $item): ?><a class="library-item <?= empty($item['read_at'])?'is-unread':'' ?>" href="<?= htmlspecialchars(site_path('/api/notification_open.php?id='.(int)$item['id'])) ?>"><div><strong><?= htmlspecialchars($item['title']) ?></strong><span><?= htmlspecialchars($item['body']) ?></span></div><time><?= date('d.m.Y H:i',strtotime($item['created_at'])) ?></time></a><?php endforeach; ?><?php if(!$notifications): ?><div class="sport-articles-empty">შეტყობინებები ჯერ არ არის.</div><?php endif; ?></div></section></main><?php require __DIR__.'/../includes/footer.php'; ?></body></html>
