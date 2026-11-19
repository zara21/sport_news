<?php
function publish_due_articles(PDO $pdo): int
{
    $due = $pdo->query("SELECT id, title, slug FROM articles WHERE status='draft' AND scheduled_at IS NOT NULL AND scheduled_at<=NOW() ORDER BY scheduled_at ASC LIMIT 100")->fetchAll();
    if (!$due) return 0;
    $update = $pdo->prepare("UPDATE articles SET status='published', scheduled_at=NULL, updated_at=NOW() WHERE id=? AND status='draft'");
    $notify = $pdo->prepare('INSERT INTO notifications (user_id, title, body, link) VALUES (NULL, ?, ?, ?)');
    $published = 0;
    foreach ($due as $article) {
        $update->execute([(int) $article['id']]);
        if ($update->rowCount()) {
            $notify->execute(['ახალი სტატია', (string) $article['title'], site_path('/news/' . rawurlencode((string) $article['slug']))]);
            $published++;
        }
    }
    return $published;
}
function run_due_scheduler(PDO $pdo): void
{
    $file=__DIR__.'/../storage/scheduler.last';
    if(is_file($file) && filemtime($file)>time()-60)return;
    publish_due_articles($pdo);
    if(!is_dir(dirname($file)))mkdir(dirname($file),0775,true);
    touch($file);
}
