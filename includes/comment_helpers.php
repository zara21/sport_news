<?php
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/admin_helpers.php';

const COMMENT_MAX_LENGTH = 2000;

function comments_for_article(PDO $pdo, int $articleId, bool $includeHidden = false): array
{
    $stmt = $pdo->prepare('SELECT c.id, c.user_id, c.body, c.status, c.created_at,
            u.first_name, u.last_name, u.profile_image
        FROM article_comments c
        JOIN users u ON u.id = c.user_id
        WHERE c.article_id = ?' . ($includeHidden ? '' : " AND c.status = 'visible'") . '
        ORDER BY c.created_at ASC, c.id ASC
        LIMIT 500');
    $stmt->execute([$articleId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function comment_can_moderate(): bool
{
    return !empty($_SESSION['user_id']) && admin_can('articles.manage');
}

/** მომხმარებლის ინიციალები ავატარისთვის */
function comment_initials(array $comment): string
{
    $first = mb_substr(trim((string) $comment['first_name']), 0, 1, 'UTF-8');
    $last = mb_substr(trim((string) $comment['last_name']), 0, 1, 'UTF-8');
    return mb_strtoupper($first . $last, 'UTF-8') ?: '?';
}

function comment_time_label(string $datetime): string
{
    $seconds = time() - strtotime($datetime);
    if ($seconds < 60) return 'ახლახან';
    if ($seconds < 3600) return floor($seconds / 60) . ' წუთის წინ';
    if ($seconds < 86400) return floor($seconds / 3600) . ' საათის წინ';
    if ($seconds < 604800) return floor($seconds / 86400) . ' დღის წინ';
    return date('d.m.Y', strtotime($datetime));
}

/** კომენტარების ბლოკი სტატიის გვერდზე */
function render_article_comments(PDO $pdo, array $article, string $returnTo): void
{
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $canModerate = comment_can_moderate();
    $comments = comments_for_article($pdo, (int) $article['id'], $canModerate);
    $visibleCount = count(array_filter($comments, static fn($c) => $c['status'] === 'visible'));
    $flash = $_SESSION['comment_flash'] ?? null;
    unset($_SESSION['comment_flash']);
    $h = static fn(?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    ?>
    <section class="article-comments" id="comments" aria-labelledby="commentsTitle">
        <h2 id="commentsTitle">კომენტარები <span><?= $visibleCount ?></span></h2>

        <?php if ($flash): ?>
            <div class="article-comments__alert article-comments__alert--<?= $flash['type'] === 'error' ? 'error' : 'success' ?>"><?= $h($flash['message']) ?></div>
        <?php endif; ?>

        <?php if ($userId): ?>
            <form class="article-comments__form" method="post" action="/sport_blog/api/comment.php">
                <input type="hidden" name="csrf_token" value="<?= $h(form_csrf_token()) ?>">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="article_id" value="<?= (int) $article['id'] ?>">
                <input type="hidden" name="return_to" value="<?= $h($returnTo) ?>">
                <label class="sr-only" for="commentBody">კომენტარი</label>
                <textarea id="commentBody" name="body" rows="3" maxlength="<?= COMMENT_MAX_LENGTH ?>" required placeholder="დაწერეთ კომენტარი…"></textarea>
                <div class="article-comments__form-footer">
                    <small>იყავით კორექტული — შეურაცხმყოფელი კომენტარები წაიშლება.</small>
                    <button type="submit">გამოქვეყნება</button>
                </div>
            </form>
        <?php else: ?>
            <p class="article-comments__login">კომენტარის დასაწერად <a href="/sport_blog/auth_system/login">შედით</a> ან <a href="/sport_blog/auth_system/register">დარეგისტრირდით</a>.</p>
        <?php endif; ?>

        <?php if (!$comments): ?>
            <p class="article-comments__empty">ჯერ კომენტარი არ არის — იყავით პირველი.</p>
        <?php else: ?>
            <ol class="article-comments__list">
                <?php foreach ($comments as $comment):
                    $isOwn = $userId === (int) $comment['user_id'];
                    $hidden = $comment['status'] === 'hidden';
                ?>
                    <li class="article-comment<?= $hidden ? ' article-comment--hidden' : '' ?>" id="comment-<?= (int) $comment['id'] ?>">
                        <span class="article-comment__avatar" aria-hidden="true"><?= $h(comment_initials($comment)) ?></span>
                        <div class="article-comment__main">
                            <div class="article-comment__meta">
                                <strong><?= $h(trim($comment['first_name'] . ' ' . $comment['last_name'])) ?></strong>
                                <time datetime="<?= $h(date('c', strtotime($comment['created_at']))) ?>"><?= $h(comment_time_label($comment['created_at'])) ?></time>
                                <?php if ($hidden): ?><span class="article-comment__badge">დამალულია</span><?php endif; ?>
                            </div>
                            <p><?= nl2br($h($comment['body'])) ?></p>
                            <?php if ($isOwn || $canModerate): ?>
                                <div class="article-comment__actions">
                                    <?php if ($canModerate): ?>
                                        <form method="post" action="/sport_blog/api/comment.php">
                                            <input type="hidden" name="csrf_token" value="<?= $h(form_csrf_token()) ?>">
                                            <input type="hidden" name="action" value="<?= $hidden ? 'show' : 'hide' ?>">
                                            <input type="hidden" name="comment_id" value="<?= (int) $comment['id'] ?>">
                                            <input type="hidden" name="return_to" value="<?= $h($returnTo) ?>">
                                            <button type="submit"><?= $hidden ? 'გამოჩენა' : 'დამალვა' ?></button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" action="/sport_blog/api/comment.php" onsubmit="return confirm('კომენტარი წაიშალოს?')">
                                        <input type="hidden" name="csrf_token" value="<?= $h(form_csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="comment_id" value="<?= (int) $comment['id'] ?>">
                                        <input type="hidden" name="return_to" value="<?= $h($returnTo) ?>">
                                        <button type="submit">წაშლა</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>
    <?php
}
