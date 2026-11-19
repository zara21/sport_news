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
    // ერთი და იგივე სახელიანი პარამეტრი ორჯერ არ შეიძლება (native prepares) — ვიყენებთ VALUES()-ს
    $stmt = $pdo->prepare("
        INSERT INTO match_iframes (slug, iframe_live)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE iframe_live = VALUES(iframe_live), updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$slug, $iframeLive]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? null);

    if (isset($_POST['delete_all'])) {
        $pdo->exec("DELETE FROM match_iframes");
        $successMessage = 'ყველა ბმული წაიშალა.';
    } elseif (isset($_POST['delete_slug'])) {
        $slug = trim((string)$_POST['delete_slug']);
        $stmt = $pdo->prepare("DELETE FROM match_iframes WHERE slug = ?");
        $stmt->execute([$slug]);
        $successMessage = 'ბმული წაიშალა.';
    } elseif (isset($_POST['slug'], $_POST['iframe_live'], $_POST['save_single'])) {
        $slug = trim((string)$_POST['slug']);
        $iframeLive = normalize_embed_url($_POST['iframe_live'] ?? '');

        $exists = $pdo->prepare('SELECT 1 FROM matches WHERE slug = ? LIMIT 1');
        $exists->execute([$slug]);

        if ($slug === '' || !$exists->fetchColumn()) {
            $errorMessage = 'აირჩიეთ მატჩი სიიდან.';
        } elseif ($iframeLive === '') {
            $errorMessage = 'ჩაწერეთ სწორი http/https ბმული ან iframe კოდი.';
        } else {
            save_iframe_live($pdo, $slug, $iframeLive);
            $successMessage = 'ბმული შეინახა.';
        }
    }
}

$matches = $pdo->query("
    SELECT m.slug, m.tournament_name, m.match_time, m.home_team, m.away_team, m.home_logo, m.away_logo,
           m.home_goals, m.away_goals, m.match_status, mi.iframe_live
    FROM matches m
    LEFT JOIN match_iframes mi ON mi.slug = m.slug
    ORDER BY m.tournament_name ASC, m.match_time ASC
")->fetchAll(PDO::FETCH_ASSOC);

$iframes = $pdo->query("
    SELECT mi.slug, mi.iframe_live, mi.updated_at, m.home_team, m.away_team, m.tournament_name
    FROM match_iframes mi
    LEFT JOIN matches m ON m.slug = mi.slug
    ORDER BY mi.updated_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

/** მატჩის მდგომარეობა: live / finished / scheduled + საჩვენებელი ტექსტი */
function iframe_match_state(array $match): array
{
    $status = trim((string) $match['match_status']);
    $lower = strtolower($status);
    if (in_array($lower, ['finished', 'after pen.', 'after et'], true)) {
        return ['finished', 'დასრულდა'];
    }
    if ($status !== '') {
        return ['live', is_numeric($status) ? "LIVE {$status}'" : 'LIVE · ' . $status];
    }
    return ['scheduled', $match['match_time'] !== '' ? $match['match_time'] : 'TBD'];
}

$groups = [];
$counts = ['all' => 0, 'live' => 0, 'scheduled' => 0, 'finished' => 0, 'stream' => 0];
foreach ($matches as $match) {
    [$state] = iframe_match_state($match);
    $groups[$match['tournament_name']][] = $match;
    $counts['all']++;
    $counts[$state]++;
    if (!empty($match['iframe_live'])) $counts['stream']++;
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

admin_page_start('მატჩის ტრანსლაცია');
admin_alert($successMessage);
admin_alert($errorMessage, 'error');
?>

<div class="iframe-manager">
    <section class="admin-card iframe-picker">
        <div class="admin-card__header">
            <div>
                <p class="admin-eyebrow">ნაბიჯი 1</p>
                <h2>აირჩიე მატჩი</h2>
            </div>
            <span class="admin-badge"><?= $counts['all'] ?> მატჩი დღეს</span>
        </div>

        <div class="iframe-picker__tools">
            <input type="search" id="matchSearch" class="iframe-picker__search" placeholder="ძებნა: გუნდი ან ტურნირი…" aria-label="მატჩის ძებნა">
            <div class="iframe-picker__filters" role="tablist" aria-label="ფილტრი">
                <button type="button" class="iframe-chip is-active" data-filter="all">ყველა <b><?= $counts['all'] ?></b></button>
                <button type="button" class="iframe-chip" data-filter="live">LIVE <b><?= $counts['live'] ?></b></button>
                <button type="button" class="iframe-chip" data-filter="scheduled">დაგეგმილი <b><?= $counts['scheduled'] ?></b></button>
                <button type="button" class="iframe-chip" data-filter="finished">დასრულებული <b><?= $counts['finished'] ?></b></button>
                <button type="button" class="iframe-chip" data-filter="stream">ტრანსლაციით <b><?= $counts['stream'] ?></b></button>
            </div>
        </div>

        <?php if (!$matches): ?>
            <p class="admin-muted">მატჩები ჯერ არ არის. გაუშვით მონაცემების იმპორტი.</p>
        <?php endif; ?>

        <div class="iframe-picker__list" id="matchList">
            <?php foreach ($groups as $tournament => $items): ?>
                <div class="iframe-group" data-group>
                    <div class="iframe-group__title"><?= h($tournament) ?></div>
                    <?php foreach ($items as $match):
                        [$state, $stateLabel] = iframe_match_state($match);
                        $hasStream = !empty($match['iframe_live']);
                        $search = mb_strtolower($match['home_team'] . ' ' . $match['away_team'] . ' ' . $tournament, 'UTF-8');
                    ?>
                        <button type="button" class="iframe-match"
                                data-match
                                data-slug="<?= h($match['slug']) ?>"
                                data-state="<?= $state ?>"
                                data-stream="<?= $hasStream ? '1' : '0' ?>"
                                data-search="<?= h($search) ?>"
                                data-title="<?= h($match['home_team'] . ' – ' . $match['away_team']) ?>"
                                data-meta="<?= h($tournament . ' · ' . $stateLabel) ?>"
                                data-iframe="<?= h($match['iframe_live'] ?? '') ?>">
                            <span class="iframe-match__state iframe-match__state--<?= $state ?>"><?= h($stateLabel) ?></span>
                            <span class="iframe-match__teams">
                                <span><?= h($match['home_team']) ?></span>
                                <?php if ($state !== 'scheduled' && is_numeric($match['home_goals'])): ?>
                                    <b><?= (int) $match['home_goals'] ?> : <?= (int) $match['away_goals'] ?></b>
                                <?php else: ?>
                                    <i>–</i>
                                <?php endif; ?>
                                <span><?= h($match['away_team']) ?></span>
                            </span>
                            <?php if ($hasStream): ?>
                                <span class="iframe-match__stream" title="ტრანსლაცია დამატებულია">▶ ტრანსლაცია</span>
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            <p class="admin-muted iframe-picker__empty" id="matchEmpty" hidden>ვერაფერი მოიძებნა.</p>
        </div>
    </section>

    <section class="admin-card iframe-editor">
        <p class="admin-eyebrow">ნაბიჯი 2</p>
        <h2>ტრანსლაციის ბმული</h2>

        <div class="iframe-editor__selected" id="selectedMatch">
            <span class="admin-muted">მარცხნივ აირჩიეთ მატჩი</span>
        </div>

        <form method="post" class="admin-form" autocomplete="off" id="iframeForm">
            <?php admin_csrf_field(); ?>
            <input type="hidden" name="slug" id="slugInput" required>
            <label>ბმული ან iframe კოდი
                <textarea name="iframe_live" id="iframeInput" rows="5" placeholder="https://example.com/live ან <iframe src=&quot;...&quot;></iframe>" required disabled></textarea>
            </label>
            <div class="admin-actions">
                <button type="submit" name="save_single" class="admin-button admin-button--primary" id="saveButton" disabled>შენახვა</button>
                <a href="#" class="admin-button admin-button--neutral" id="viewMatch" target="_blank" rel="noopener" hidden>მატჩის გვერდი ↗</a>
            </div>
        </form>
    </section>
</div>

<section class="admin-card">
    <div class="admin-card__header">
        <h2>დამატებული ტრანსლაციები</h2>
        <?php if ($iframes): ?>
            <form method="post" class="admin-inline-form" onsubmit="return confirm('ყველა ბმული წაიშალოს?')">
                <?php admin_csrf_field(); ?>
                <button type="submit" name="delete_all" class="admin-button admin-button--danger">ყველას წაშლა</button>
            </form>
        <?php endif; ?>
    </div>
    <?php if (!$iframes): ?>
        <p class="admin-muted">ჯერ არცერთი ტრანსლაცია არ დამატებულა.</p>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>მატჩი</th>
                        <th>ბმული</th>
                        <th>განახლდა</th>
                        <th>მოქმედება</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($iframes as $row): ?>
                    <tr>
                        <td>
                            <?php if ($row['home_team'] !== null): ?>
                                <strong><?= h($row['home_team']) ?> – <?= h($row['away_team']) ?></strong>
                                <br><small class="admin-muted"><?= h($row['tournament_name']) ?></small>
                            <?php else: ?>
                                <span class="admin-muted"><?= h($row['slug']) ?> (მატჩი დღევანდელ სიაში აღარ არის)</span>
                            <?php endif; ?>
                        </td>
                        <td><?= h(mb_strimwidth($row['iframe_live'], 0, 70, '...')) ?></td>
                        <td><?= h($row['updated_at']) ?></td>
                        <td>
                            <form method="post" class="admin-inline-form" onsubmit="return confirm('ბმული წაიშალოს?')">
                                <?php admin_csrf_field(); ?>
                                <input type="hidden" name="delete_slug" value="<?= h($row['slug']) ?>">
                                <button type="submit" class="admin-button admin-button--danger">წაშლა</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<script>
(() => {
    const search = document.getElementById('matchSearch');
    const chips = document.querySelectorAll('.iframe-chip');
    const matches = [...document.querySelectorAll('[data-match]')];
    const groups = [...document.querySelectorAll('[data-group]')];
    const empty = document.getElementById('matchEmpty');
    const selected = document.getElementById('selectedMatch');
    const slugInput = document.getElementById('slugInput');
    const iframeInput = document.getElementById('iframeInput');
    const saveButton = document.getElementById('saveButton');
    const viewMatch = document.getElementById('viewMatch');
    let filter = 'all';

    const apply = () => {
        const term = search.value.trim().toLowerCase();
        let visible = 0;
        matches.forEach((el) => {
            const okFilter = filter === 'all' || (filter === 'stream' ? el.dataset.stream === '1' : el.dataset.state === filter);
            const okSearch = !term || el.dataset.search.includes(term);
            el.hidden = !(okFilter && okSearch);
            if (!el.hidden) visible++;
        });
        groups.forEach((group) => {
            group.hidden = !group.querySelector('[data-match]:not([hidden])');
        });
        empty.hidden = visible > 0;
    };

    search.addEventListener('input', apply);
    chips.forEach((chip) => chip.addEventListener('click', () => {
        chips.forEach((c) => c.classList.toggle('is-active', c === chip));
        filter = chip.dataset.filter;
        apply();
    }));

    const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));

    matches.forEach((el) => el.addEventListener('click', () => {
        matches.forEach((m) => m.classList.toggle('is-selected', m === el));
        slugInput.value = el.dataset.slug;
        iframeInput.value = el.dataset.iframe;
        iframeInput.disabled = false;
        saveButton.disabled = false;
        selected.innerHTML = '<strong>' + escapeHtml(el.dataset.title) + '</strong><span>' + escapeHtml(el.dataset.meta) + '</span>';
        viewMatch.href = '/sport_blog/matches/' + encodeURIComponent(el.dataset.slug);
        viewMatch.hidden = false;
        iframeInput.focus();
        if (window.matchMedia('(max-width: 1100px)').matches) {
            document.getElementById('iframeForm').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }));
})();
</script>

<?php admin_page_end(); ?>
