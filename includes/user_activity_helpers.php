<?php

function record_article_read(PDO $pdo, int $userId, int $articleId): void
{
    if ($userId <= 0 || $articleId <= 0) return;
    $stmt = $pdo->prepare('INSERT INTO article_reads (user_id, article_id, read_count) VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE read_count = read_count + 1, last_read_at = CURRENT_TIMESTAMP');
    $stmt->execute([$userId, $articleId]);
}

function article_is_bookmarked(PDO $pdo, int $userId, int $articleId): bool
{
    if ($userId <= 0) return false;
    $stmt = $pdo->prepare('SELECT 1 FROM article_bookmarks WHERE user_id = ? AND article_id = ?');
    $stmt->execute([$userId, $articleId]);
    return (bool) $stmt->fetchColumn();
}

function unread_notification_count(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications n
        LEFT JOIN notification_reads nr ON nr.notification_id = n.id AND nr.user_id = ?
        WHERE (n.user_id IS NULL OR n.user_id = ?) AND nr.notification_id IS NULL');
    $stmt->execute([$userId, $userId]);
    return (int) $stmt->fetchColumn();
}
