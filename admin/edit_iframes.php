<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_once __DIR__ . '/../includes/embed_helpers.php';
require_admin_user();

$successMessage = '';
$errorMessage = '';

function save_iframe_live(PDO $pdo, string $slug, string $iframeLive): void
{
    $stmt = $pdo->prepare("
        INSERT INTO match_iframes (slug, iframe_live)
        VALUES (:slug, :iframe_live)
        ON DUPLICATE KEY UPDATE iframe_live = :iframe_live
    ");
    $stmt->execute([
        ':slug' => $slug,
        ':iframe_live' => $iframeLive,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? null);

    if (isset($_POST['delete_all'])) {
        $pdo->exec("DELETE FROM match_iframes");
        $successMessage = 'ყველა ჩანაწერი წაიშალა.';
    } elseif (isset($_POST['delete_slug'])) {
        $slug = trim((string)$_POST['delete_slug']);
        $stmt = $pdo->prepare("DELETE FROM match_iframes WHERE slug = ?");
        $stmt->execute([$slug]);
        $successMessage = 'ჩანაწერი წაიშალა.';
    } elseif (isset($_POST['slug'], $_POST['iframe_live'], $_POST['save_single'])) {
        $slug = safe_slug(trim((string)$_POST['slug']), 'match');
        $iframeLive = normalize_embed_url($_POST['iframe_live'] ?? '');

        if ($slug === '' || $iframeLive === '') {
            $errorMessage = 'აირჩიეთ მატჩი და ჩაწერეთ სწორი http/https ბმული ან iframe კოდი.';
        } else {
            save_iframe_live($pdo, $slug, $iframeLive);
            $successMessage = 'ინფორმაცია წარმატებით შეინახა.';
        }
    }
}

$iframes = $pdo->query("SELECT slug, iframe_live, updated_at FROM match_iframes ORDER BY updated_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$matches = $pdo->query("SELECT slug, home_team, away_team FROM matches ORDER BY match_time ASC")->fetchAll(PDO::FETCH_ASSOC);

admin_page_start('მატჩის iframe მართვა');
admin_alert($successMessage);
admin_alert($errorMessage, 'error');
?>

<section class="admin-card">
    <div class="admin-card__header">
        <h2>iframe დამატება / განახლება</h2>
    </div>
    <form method="post" class="admin-form" autocomplete="off">
        <?php admin_csrf_field(); ?>
        <label>აირჩიე მატჩი
            <select id="match_selector">
                <option value="">აირჩიე მატჩი</option>
                <?php foreach ($matches as $match): ?>
                    <option value="<?= htmlspecialchars($match['slug'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($match['slug'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars($match['home_team'], ENT_QUOTES, 'UTF-8') ?> vs <?= htmlspecialchars($match['away_team'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Slug
            <input type="text" id="slug_input" name="slug" placeholder="india-w-vs-iraq-w" required>
        </label>
        <label>Iframe live კოდი
            <textarea name="iframe_live" placeholder="https://example.com/live ან <iframe src=&quot;...&quot;></iframe>" required></textarea>
        </label>
        <div class="admin-actions">
            <button type="submit" name="save_single" class="admin-button admin-button--primary">შენახვა</button>
        </div>
    </form>
</section>

<section class="admin-card">
    <div class="admin-card__header">
        <h2>არსებული ჩანაწერები</h2>
        <form method="post" class="admin-inline-form" onsubmit="return confirm('ყველა ჩანაწერი წაიშალოს?')">
            <?php admin_csrf_field(); ?>
            <button type="submit" name="delete_all" class="admin-button--danger">ყველას წაშლა</button>
        </form>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Slug</th>
                    <th>Iframe live</th>
                    <th>განახლდა</th>
                    <th>მოქმედება</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($iframes as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['slug'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(mb_strimwidth($row['iframe_live'], 0, 90, '...'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row['updated_at'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <form method="post" class="admin-inline-form" onsubmit="return confirm('ჩანაწერი წაიშალოს?')">
                            <?php admin_csrf_field(); ?>
                            <input type="hidden" name="delete_slug" value="<?= htmlspecialchars($row['slug'], ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="admin-button--danger">წაშლა</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
document.getElementById('match_selector').addEventListener('change', function () {
    document.getElementById('slug_input').value = this.value;
});
</script>

<?php admin_page_end(); ?>
