<?php
session_start();
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/admin_layout.php';
require_once __DIR__ . '/../../includes/cache_helpers.php';
require_once __DIR__ . '/../../includes/sports_import_helpers.php';
require_admin_user('system.import');

$successMessage = '';
$errorMessage = '';
$preview = [];
$selectedAction = $_POST['import_action'] ?? 'all';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? null);
    try {
        if (isset($_POST['preview_import'])) {
            $preview = sports_preview($pdo, $selectedAction, __DIR__);
        } elseif (isset($_POST['apply_import'])) {
            $run = $pdo->prepare('INSERT INTO data_sync_runs (sync_type, status) VALUES (?, "running")');
            $run->execute([$selectedAction]);
            $runId = (int) $pdo->lastInsertId();
            try {
                $counts = sports_apply($pdo, $selectedAction, __DIR__);
                $changed = array_sum($counts);
                $pdo->prepare('UPDATE data_sync_runs SET status="success", rows_changed=?, message=?, finished_at=NOW() WHERE id=?')
                    ->execute([$changed, json_encode($counts), $runId]);
                cache_clear_all();
                $successMessage = "განახლდა {$counts['matches']} მატჩი და {$counts['standings']} სატურნირო ჩანაწერი.";
            } catch (Throwable $e) {
                $pdo->prepare('UPDATE data_sync_runs SET status="failed", message=?, finished_at=NOW() WHERE id=?')->execute([$e->getMessage(), $runId]);
                throw $e;
            }
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$recentRuns = $pdo->query('SELECT sync_type, status, rows_changed, message, started_at, finished_at FROM data_sync_runs ORDER BY id DESC LIMIT 10')->fetchAll();
admin_page_start('მონაცემების იმპორტი');
?>
<section class="admin-card">
    <div class="admin-card__header"><div><p class="admin-eyebrow">CSV Import</p><h2>მატჩებისა და ცხრილების განახლება</h2></div></div>
    <p class="admin-muted">ჯერ გამოიყენეთ წინასწარი ნახვა. მონაცემები ბაზაში მხოლოდ „ცვლილებების შენახვის“ დაჭერის შემდეგ ჩაიწერება.</p>
    <?php admin_alert($successMessage); ?><?php admin_alert($errorMessage, 'error'); ?>
    <form method="post" class="admin-form">
        <?php admin_csrf_field(); ?>
        <label>განახლების ტიპი
            <select name="import_action" required>
                <option value="all" <?= $selectedAction === 'all' ? 'selected' : '' ?>>ყველა მონაცემი</option>
                <option value="matches" <?= $selectedAction === 'matches' ? 'selected' : '' ?>>მხოლოდ მატჩები</option>
                <option value="standings" <?= $selectedAction === 'standings' ? 'selected' : '' ?>>მხოლოდ სატურნირო ცხრილები</option>
            </select>
        </label>
        <div class="admin-actions">
            <button type="submit" name="preview_import" value="1" class="admin-button">წინასწარი ნახვა</button>
            <button type="submit" name="apply_import" value="1" class="admin-button admin-button--primary" onclick="return confirm('ნამდვილად გსურთ ცვლილებების შენახვა?')">ცვლილებების შენახვა</button>
        </div>
    </form>
</section>

<?php if ($preview): ?>
<section class="admin-card">
    <div class="admin-card__header"><div><p class="admin-eyebrow">Preview</p><h2>მოსალოდნელი ცვლილებები</h2></div></div>
    <div class="admin-stat-grid">
        <?php foreach ($preview as $name => $diff): ?>
            <article class="admin-stat-card"><span><?= $name === 'matches' ? 'მატჩები' : 'ცხრილები' ?></span><strong><?= (int) $diff['incoming'] ?></strong><small>ახალი <?= (int) $diff['added'] ?> · შეცვლილი <?= (int) $diff['updated'] ?> · წასაშლელი <?= (int) $diff['removed'] ?></small></article>
        <?php endforeach; ?>
    </div>
    <?php foreach ($preview as $name => $diff): ?>
        <h3><?= $name === 'matches' ? 'მატჩების ნიმუში' : 'ცხრილების ნიმუში' ?></h3>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>სახელი</th><th>დეტალი</th><th>სტატუსი</th></tr></thead><tbody>
        <?php foreach ($diff['sample'] as $row): ?><tr>
            <td><?= htmlspecialchars($row['home_team'] ?? $row['team'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars(isset($row['away_team']) ? 'vs ' . $row['away_team'] : ($row['league_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['match_status'] ?? ('#' . ($row['position'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
        </tr><?php endforeach; ?>
        </tbody></table></div>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<section class="admin-card">
    <div class="admin-card__header"><div><p class="admin-eyebrow">History</p><h2>ბოლო განახლებები</h2></div></div>
    <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>ტიპი</th><th>სტატუსი</th><th>ჩანაწერები</th><th>დრო</th></tr></thead><tbody>
    <?php foreach ($recentRuns as $run): ?><tr><td><?= htmlspecialchars($run['sync_type'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($run['status'], ENT_QUOTES, 'UTF-8') ?></td><td><?= (int) $run['rows_changed'] ?></td><td><?= htmlspecialchars($run['started_at'], ENT_QUOTES, 'UTF-8') ?></td></tr><?php endforeach; ?>
    <?php if (!$recentRuns): ?><tr><td colspan="4">განახლების ისტორია ჯერ არ არის.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>
<?php admin_page_end(); ?>
