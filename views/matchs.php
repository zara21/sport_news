<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/home_match_helpers.php';

$allMatches = home_match_feed($pdo);
$matches = array_slice($allMatches, 0, 12);
?>

<?php if ($matches): ?>
<section class="home-match-strip" aria-labelledby="matchStripTitle">
    <div class="home-strip-heading">
        <div>
            <h2 id="matchStripTitle">დღის დარჩენილი პროგრამა</h2>
        </div>
        <div class="home-strip-controls">
            <?php if (count($allMatches) > count($matches)): ?><button type="button" class="home-match-more" id="loadMoreMatches" data-offset="12" data-total="<?= count($allMatches) ?>">დარჩენილი მატჩები</button><?php endif; ?>
            <button type="button" class="home-icon-button" data-scroll-target="matchGrid" data-scroll-direction="-1" aria-label="წინა მატჩები">&#8249;</button>
            <button type="button" class="home-icon-button" data-scroll-target="matchGrid" data-scroll-direction="1" aria-label="შემდეგი მატჩები">&#8250;</button>
        </div>
    </div>
    <div class="home-match-grid" id="matchGrid" tabindex="0" aria-label="მატჩების ჰორიზონტალური სია">
        <?php foreach ($matches as $match) require __DIR__ . '/match_card.php'; ?>
    </div>
</section>
<?php endif; ?>
