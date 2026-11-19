<?php
require_once __DIR__ . '/../includes/auth_helpers.php';
auth_start_session();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/media_helpers.php';
if (empty($_SESSION['user_id'])) { site_redirect('/auth_system/login'); }
$userId = (int) $_SESSION['user_id'];
$tab = (string) ($_GET['tab'] ?? 'bookmarks');
if (!in_array($tab, ['bookmarks', 'reading', 'quizzes'], true)) $tab = 'bookmarks';
$items = [];
if ($tab === 'bookmarks') {
    $stmt = $pdo->prepare('SELECT a.title,a.slug,a.image,a.summary,b.created_at activity_at FROM article_bookmarks b JOIN articles a ON a.id=b.article_id WHERE b.user_id=? AND a.status="published" ORDER BY b.created_at DESC LIMIT 100');
    $stmt->execute([$userId]); $items = $stmt->fetchAll();
} elseif ($tab === 'reading') {
    $stmt = $pdo->prepare('SELECT a.title,a.slug,a.image,a.summary,r.last_read_at activity_at,r.read_count FROM article_reads r JOIN articles a ON a.id=r.article_id WHERE r.user_id=? AND a.status="published" ORDER BY r.last_read_at DESC LIMIT 100');
    $stmt->execute([$userId]); $items = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare('(SELECT q.title,q.slug,qr.score,qr.taken_at activity_at,(SELECT COUNT(*) FROM questions WHERE quiz_id=q.id) total FROM quiz_results qr JOIN quizzes q ON q.id=qr.quiz_id WHERE qr.user_id=?) UNION ALL (SELECT qg.title,qg.slug,qgr.score,qgr.taken_at activity_at,qgr.total FROM quiz_game_results qgr JOIN quiz_games qg ON qg.id=qgr.quiz_game_id WHERE qgr.user_id=?) ORDER BY activity_at DESC LIMIT 100');
    $stmt->execute([$userId,$userId]); $items = $stmt->fetchAll();
}
?>
<!doctype html><html lang="ka"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ჩემი ბიბლიოთეკა | Sport Blog</title><link rel="stylesheet" href="/sport_blog/css/styles.css?v=20261119c"><link rel="stylesheet" href="/sport_blog/css/navbar.css?v=20261119c"><link rel="stylesheet" href="/sport_blog/css/profile.css?v=20261119c"></head><body><?php require __DIR__.'/../includes/header.php'; ?>
<main class="profile-shell"><section class="user-library"><header class="profile-settings__heading"><div><p class="user-profile__eyebrow">პირადი სივრცე</p><h1>ჩემი ბიბლიოთეკა</h1></div><a class="user-profile__button user-profile__button--neutral" href="/sport_blog/views/profile">პროფილი</a></header>
<nav class="library-tabs"><a class="<?= $tab==='bookmarks'?'is-active':'' ?>" href="?tab=bookmarks">რჩეულები</a><a class="<?= $tab==='reading'?'is-active':'' ?>" href="?tab=reading">წაკითხული</a><a class="<?= $tab==='quizzes'?'is-active':'' ?>" href="?tab=quizzes">ქვიზები</a></nav>
<?php if(!$items): ?><div class="sport-articles-empty">ჩანაწერები ჯერ არ არის.</div><?php else: ?><div class="library-list"><?php foreach($items as $item): ?>
<?php if($tab==='quizzes'): ?><a class="library-item" href="/sport_blog/quizzes/<?= htmlspecialchars($item['slug']) ?>"><div><strong><?= htmlspecialchars($item['title']) ?></strong><span>ქულა: <?= (int)$item['score'] ?> / <?= (int)$item['total'] ?></span></div><time><?= date('d.m.Y H:i',strtotime($item['activity_at'])) ?></time></a>
<?php else: ?><article class="library-item"><img src="<?= htmlspecialchars(article_image_url($item['image']??null)) ?>" width="120" height="68" alt=""><div><a href="/sport_blog/news/<?= htmlspecialchars($item['slug']) ?>"><strong><?= htmlspecialchars($item['title']) ?></strong></a><span><?= htmlspecialchars(mb_strimwidth(strip_tags($item['summary']??''),0,110,'...')) ?></span></div><time><?= date('d.m.Y H:i',strtotime($item['activity_at'])) ?></time></article><?php endif; ?>
<?php endforeach; ?></div><?php endif; ?></section></main><?php require __DIR__.'/../includes/footer.php'; ?></body></html>
