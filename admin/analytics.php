<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_admin_user('dashboard');

$totals = [
    'სტატიები' => (int)$pdo->query("SELECT COUNT(*) FROM articles WHERE status='published'")->fetchColumn(),
    'ნახვები' => (int)$pdo->query('SELECT COALESCE(SUM(views),0) FROM articles')->fetchColumn(),
    'წაკითხვები' => (int)$pdo->query('SELECT COALESCE(SUM(read_count),0) FROM article_reads')->fetchColumn(),
    'რჩეულები' => (int)$pdo->query('SELECT COUNT(*) FROM article_bookmarks')->fetchColumn(),
    'ქვიზის მცდელობები' => (int)$pdo->query('SELECT (SELECT COUNT(*) FROM quiz_results)+(SELECT COUNT(*) FROM quiz_game_results)')->fetchColumn(),
    'Story clicks' => (int)$pdo->query('SELECT COUNT(*) FROM story_clicks')->fetchColumn(),
];
$popular = $pdo->query("SELECT title,views FROM articles WHERE status='published' ORDER BY views DESC LIMIT 10")->fetchAll();
$tags = $pdo->query("SELECT t.name,COUNT(*) uses FROM article_tags at JOIN tags t ON t.id=at.tag_id JOIN articles a ON a.id=at.article_id WHERE a.status='published' GROUP BY t.id ORDER BY uses DESC LIMIT 10")->fetchAll();
$stories = $pdo->query("SELECT a.title,COUNT(sc.id) clicks FROM story_clicks sc JOIN articles a ON a.id=sc.article_id GROUP BY a.id ORDER BY clicks DESC LIMIT 10")->fetchAll();
$syncRuns = $pdo->query('SELECT sync_type,status,rows_changed,started_at FROM data_sync_runs ORDER BY id DESC LIMIT 8')->fetchAll();
admin_page_start('ანალიტიკა');
?>
<section class="admin-stat-grid">
    <?php foreach($totals as $label=>$value): ?><article class="admin-stat-card"><span><?= htmlspecialchars($label,ENT_QUOTES,'UTF-8') ?></span><strong><?= number_format($value) ?></strong></article><?php endforeach; ?>
</section>
<div class="admin-dashboard-grid">
    <section class="admin-card"><div class="admin-card__header"><h2>პოპულარული სტატიები</h2></div><div class="admin-list"><?php foreach($popular as $row): ?><div class="admin-list-item"><strong><?= htmlspecialchars($row['title'],ENT_QUOTES,'UTF-8') ?></strong><span><?= (int)$row['views'] ?> ნახვა</span></div><?php endforeach; ?></div></section>
    <section class="admin-card"><div class="admin-card__header"><h2>პოპულარული თეგები</h2></div><div class="admin-list"><?php foreach($tags as $row): ?><div class="admin-list-item"><strong><?= htmlspecialchars($row['name'],ENT_QUOTES,'UTF-8') ?></strong><span><?= (int)$row['uses'] ?> სტატია</span></div><?php endforeach; ?></div></section>
</div>
<div class="admin-dashboard-grid">
    <section class="admin-card"><div class="admin-card__header"><h2>Story clicks</h2></div><div class="admin-list"><?php foreach($stories as $row): ?><div class="admin-list-item"><strong><?= htmlspecialchars($row['title'],ENT_QUOTES,'UTF-8') ?></strong><span><?= (int)$row['clicks'] ?> click</span></div><?php endforeach; ?><?php if(!$stories): ?><p class="admin-muted">Story click ჯერ არ დაფიქსირებულა.</p><?php endif; ?></div></section>
    <section class="admin-card"><div class="admin-card__header"><h2>ბოლო ავტომატური განახლებები</h2></div><div class="admin-list"><?php foreach($syncRuns as $row): ?><div class="admin-list-item"><div><strong><?= htmlspecialchars($row['sync_type'],ENT_QUOTES,'UTF-8') ?></strong><span><?= htmlspecialchars($row['started_at'],ENT_QUOTES,'UTF-8') ?></span></div><span><?= htmlspecialchars($row['status'],ENT_QUOTES,'UTF-8') ?> · <?= (int)$row['rows_changed'] ?></span></div><?php endforeach; ?><?php if(!$syncRuns): ?><p class="admin-muted">განახლების ისტორია ჯერ არ არის.</p><?php endif; ?></div></section>
</div>
<?php admin_page_end(); ?>
