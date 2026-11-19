<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/admin_layout.php';

function admin_count(PDO $pdo, string $table, string $where = ''): int
{
    $sql = "SELECT COUNT(*) FROM {$table}" . ($where ? " WHERE {$where}" : '');
    return (int)$pdo->query($sql)->fetchColumn();
}

$stats = [
    ['label' => 'სტატიები', 'value' => admin_count($pdo, 'articles'), 'href' => 'admin_articles.php'],
    ['label' => 'დრაფტები', 'value' => admin_count($pdo, 'articles', "status = 'draft'"), 'href' => 'admin_articles.php'],
    ['label' => 'ქვიზები', 'value' => admin_count($pdo, 'quizzes'), 'href' => 'quizzes.php'],
    ['label' => 'მატჩები', 'value' => admin_count($pdo, 'matches'), 'href' => 'edit_iframes.php'],
    ['label' => 'სთორები', 'value' => admin_count($pdo, 'articles', "show_in_story = 1 AND (story_expires_at IS NULL OR story_expires_at > NOW())"), 'href' => 'stories.php'],
    ['label' => 'მომხმარებლები', 'value' => admin_count($pdo, 'users'), 'href' => 'user_list.php'],
];

$recentArticles = $pdo->query("
    SELECT a.id, a.title, a.slug, a.status, a.created_at, u.first_name, u.last_name
    FROM articles a
    LEFT JOIN users u ON u.id = a.user_id
    ORDER BY a.created_at DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

$recentQuizzes = $pdo->query("
    SELECT id, title, slug, created_at
    FROM quizzes
    ORDER BY created_at DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

$events = $pdo->query("
    SELECT id, sport_type, event_title, event_date, event_time, status
    FROM sports_events
    ORDER BY event_date ASC, event_time ASC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

$matches = $pdo->query("
    SELECT slug, tournament_name, match_time, home_team, away_team, match_status
    FROM matches
    ORDER BY match_time ASC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

admin_page_start('ადმინის პანელი');
?>

<section class="admin-dashboard">
    <div class="admin-stat-grid">
        <?php foreach ($stats as $stat): ?>
            <a class="admin-stat-card" href="<?= htmlspecialchars($stat['href'], ENT_QUOTES, 'UTF-8') ?>">
                <span><?= htmlspecialchars($stat['label'], ENT_QUOTES, 'UTF-8') ?></span>
                <strong><?= number_format($stat['value']) ?></strong>
            </a>
        <?php endforeach; ?>
    </div>

    <section class="admin-card admin-quick-actions">
        <div class="admin-card__header">
            <h2>სწრაფი მოქმედებები</h2>
        </div>
        <div class="admin-action-grid">
            <a href="add_article.php" class="admin-action-tile">
                <strong>სტატიის დამატება</strong>
                <span>სიახლე, ფოტო, სთორი და თეგები</span>
            </a>
            <a href="stories.php" class="admin-action-tile">
                <strong>სთორების მართვა</strong>
                <span>ვადა, აქტიური სთორები და კლუბების დაფარვა</span>
            </a>
            <a href="media.php" class="admin-action-tile">
                <strong>მედია ბიბლიოთეკა</strong>
                <span>ფაილების ნახვა და გამოუყენებლის წაშლა</span>
            </a>
            <a href="add_quiz.php" class="admin-action-tile">
                <strong>ქვიზის შექმნა</strong>
                <span>კითხვები, პასუხები და სურათი</span>
            </a>
            <a href="edit_iframes.php" class="admin-action-tile">
                <strong>მატჩის iframe</strong>
                <span>ლაივ ჩანართების მართვა</span>
            </a>
            <a href="manage_UFC_F1_event.php" class="admin-action-tile">
                <strong>UFC / F1 ივენთები</strong>
                <span>ივენთების კალენდარი</span>
            </a>
        </div>
    </section>

    <div class="admin-dashboard-grid">
        <section class="admin-card">
            <div class="admin-card__header">
                <h2>ბოლო სტატიები</h2>
                <a href="admin_articles.php" class="admin-button admin-button--neutral">ყველა</a>
            </div>
            <div class="admin-table-wrap">
                <table class="admin-table admin-table--compact">
                    <thead>
                        <tr>
                            <th>სათაური</th>
                            <th>ავტორი</th>
                            <th>სტატუსი</th>
                            <th>მოქმედება</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentArticles as $article): ?>
                        <tr>
                            <td><?= htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(trim(($article['first_name'] ?? '') . ' ' . ($article['last_name'] ?? '')) ?: 'უცნობი', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="admin-badge"><?= htmlspecialchars($article['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td>
                                <div class="admin-actions">
                                    <a href="/sport_blog/news/<?= htmlspecialchars($article['slug'], ENT_QUOTES, 'UTF-8') ?>" class="admin-button admin-button--neutral" target="_blank" rel="noopener noreferrer">ნახვა</a>
                                    <a href="edit_article.php?id=<?= (int)$article['id'] ?>" class="admin-button admin-button--neutral">რედაქტირება</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="admin-card">
            <div class="admin-card__header">
                <h2>ბოლო ქვიზები</h2>
                <a href="quizzes.php" class="admin-button admin-button--neutral">ყველა</a>
            </div>
            <div class="admin-list">
                <?php foreach ($recentQuizzes as $quiz): ?>
                    <div class="admin-list-item">
                        <div>
                            <strong><?= htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <span><?= htmlspecialchars(date('Y-m-d', strtotime($quiz['created_at'])), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <a href="edit_quiz.php?id=<?= (int)$quiz['id'] ?>" class="admin-button admin-button--neutral">რედაქტირება</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <div class="admin-dashboard-grid">
        <section class="admin-card">
            <div class="admin-card__header">
                <h2>უახლოესი მატჩები</h2>
                <a href="edit_iframes.php" class="admin-button admin-button--neutral">iframe მართვა</a>
            </div>
            <div class="admin-list">
                <?php foreach ($matches as $match): ?>
                    <div class="admin-list-item">
                        <div>
                            <strong><?= htmlspecialchars($match['home_team'] . ' vs ' . $match['away_team'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <span><?= htmlspecialchars(($match['tournament_name'] ?? '') . ' · ' . ($match['match_time'] ?? '') . ' · ' . ($match['match_status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <a href="/sport_blog/matches/<?= htmlspecialchars($match['slug'], ENT_QUOTES, 'UTF-8') ?>" class="admin-button admin-button--neutral" target="_blank" rel="noopener noreferrer">ნახვა</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="admin-card">
            <div class="admin-card__header">
                <h2>UFC / F1 ივენთები</h2>
                <a href="add_UFC_F1_event.php" class="admin-button admin-button--primary">დამატება</a>
            </div>
            <div class="admin-list">
                <?php foreach ($events as $event): ?>
                    <div class="admin-list-item">
                        <div>
                            <strong><?= htmlspecialchars($event['event_title'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <span><?= htmlspecialchars($event['sport_type'] . ' · ' . $event['event_date'] . ' ' . $event['event_time'] . ' · ' . $event['status'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <a href="edit_UFC_F1_event.php?id=<?= (int)$event['id'] ?>" class="admin-button admin-button--neutral">რედაქტირება</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</section>

<?php admin_page_end(); ?>
