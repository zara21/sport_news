<?php
require_once __DIR__ . '/brand.php';
require_once __DIR__ . '/auth_helpers.php';

if (!empty($_SESSION['user_id']) && isset($pdo) && $pdo instanceof PDO) {
    require_once __DIR__ . '/user_activity_helpers.php';
    $headerUnreadCount = unread_notification_count($pdo, (int) $_SESSION['user_id']);
} else {
    $headerUnreadCount = 0;
}

$headerIsLoggedIn = !empty($_SESSION['user_id']);
$headerPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$headerCategories = [
    'football' => ['ფეხბურთი', 'fa-futbol'],
    'basketball' => ['კალათბურთი', 'fa-basketball-ball'],
    'tennis' => ['ჩოგბურთი', 'fa-table-tennis'],
    'formula1' => ['ფორმულა 1', 'fa-flag-checkered'],
    'mma' => ['MMA', 'fa-hand-fist'],
    'rugby' => ['რაგბი', 'fa-football-ball'],
    'other' => ['სხვა', 'fa-ellipsis-h'],
];
$headerQuickLinks = [
    ['/sport_blog/views/articles', 'სიახლეები', '/views/articles'],
    ['/sport_blog/views/all_quizzes', 'ქვიზები', '/views/all_quizzes'],
    ['/sport_blog/#video-news', 'ვიდეოები', null],
    ['/sport_blog/#standings', 'ცხრილები', null],
];
$headerIsActive = static fn(?string $needle): bool => $needle !== null && str_contains($headerPath, $needle);
?>
<script>
  (() => {
    try {
      const saved = localStorage.getItem('sb-theme');
      if (saved === 'light' || saved === 'dark') document.documentElement.dataset.theme = saved;
    } catch (e) {}
  })();
</script>
<header class="sport-navbar" aria-label="მთავარი ნავიგაცია">
  <div class="sport-navbar__bar">
    <div class="sport-navbar__logo-wrapper">
      <?php sb_render_logo(); ?>
    </div>

    <nav class="sport-navbar__center" aria-label="სწრაფი ბმულები">
      <?php foreach ($headerQuickLinks as [$headerHref, $headerLabel, $headerMatch]): ?>
        <a href="<?= $headerHref ?>" class="sport-navbar__main-link<?= $headerIsActive($headerMatch) ? ' sport-navbar__main-link--active' : '' ?>"><?= $headerLabel ?></a>
      <?php endforeach; ?>
    </nav>

    <form class="sport-navbar__search" role="search" action="/sport_blog/views/articles" method="get">
      <input type="search" name="q" aria-label="სტატიების ძებნა" placeholder="სტატიების ძებნა" maxlength="120" required
             value="<?= htmlspecialchars($articleSearch ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      <button type="submit" aria-label="ძებნა"><i class="fas fa-search" aria-hidden="true"></i></button>
    </form>

    <div class="sport-navbar__auth">
      <?php sb_render_theme_toggle(); ?>
      <?php if (!$headerIsLoggedIn): ?>
        <a href="/sport_blog/auth_system/login" class="sport-navbar__auth-button sport-navbar__auth-button--login">შესვლა</a>
        <a href="/sport_blog/auth_system/register" class="sport-navbar__auth-button sport-navbar__auth-button--register">რეგისტრაცია</a>
      <?php else: ?>
        <a href="/sport_blog/views/notifications" class="sport-navbar__notification" aria-label="შეტყობინებები"><i class="fas fa-bell"></i><?php if ($headerUnreadCount): ?><span><?= min(99, $headerUnreadCount) ?></span><?php endif; ?></a>
        <a href="/sport_blog/views/profile" class="sport-navbar__auth-button sport-navbar__auth-button--login"><i class="fas fa-user"></i> პროფილი</a>
        <form action="/sport_blog/auth_system/logout" method="post">
          <?php render_logout_token(); ?>
          <button type="submit" class="sport-navbar__icon-button" aria-label="გასვლა" title="გასვლა"><i class="fas fa-sign-out-alt" aria-hidden="true"></i></button>
        </form>
      <?php endif; ?>
    </div>

    <div class="sport-navbar__mobile-actions">
      <?php sb_render_theme_toggle(); ?>
      <button class="sport-navbar__hamburger-icon" type="button" aria-label="მენიუს გახსნა" aria-expanded="false" aria-controls="mobileMenu">
        <i class="fas fa-bars"></i>
      </button>
    </div>
  </div>

  <nav class="sport-navbar__sports" aria-label="კატეგორიები">
    <div class="sport-navbar__sports-inline">
      <?php foreach ($headerCategories as $headerCategorySlug => [$headerLabel, $headerIcon]): ?>
        <a href="/sport_blog/category/<?= $headerCategorySlug ?>" class="sport-navbar__sports-item<?= $headerIsActive('/category/' . $headerCategorySlug) ? ' sport-navbar__sports-item--active' : '' ?>"><i class="fas <?= $headerIcon ?>"></i> <?= $headerLabel ?></a>
      <?php endforeach; ?>
    </div>
  </nav>

</header>

<div class="sport-navbar__mobile-menu" id="mobileMenu" aria-hidden="true">
  <div class="sport-navbar__mobile-head">
    <?php sb_render_logo(); ?>
    <button class="sport-navbar__mobile-close" type="button" aria-label="მენიუს დახურვა">&times;</button>
  </div>

  <div class="sport-navbar__mobile-section">
    <span class="sport-navbar__mobile-title">კატეგორიები</span>
    <div class="sport-navbar__mobile-grid">
      <?php foreach ($headerCategories as $headerCategorySlug => [$headerLabel, $headerIcon]): ?>
        <a href="/sport_blog/category/<?= $headerCategorySlug ?>" class="sport-navbar__main-link"><i class="fas <?= $headerIcon ?>"></i> <?= $headerLabel ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="sport-navbar__mobile-section">
    <span class="sport-navbar__mobile-title">სწრაფი ბმულები</span>
    <div class="sport-navbar__mobile-grid">
      <?php foreach ($headerQuickLinks as [$headerHref, $headerLabel]): ?>
        <a href="<?= $headerHref ?>" class="sport-navbar__main-link"><?= $headerLabel ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="sport-navbar__mobile-auth">
    <?php if (!$headerIsLoggedIn): ?>
      <a href="/sport_blog/auth_system/login" class="sport-navbar__auth-button sport-navbar__auth-button--login">შესვლა</a>
      <a href="/sport_blog/auth_system/register" class="sport-navbar__auth-button sport-navbar__auth-button--register">რეგისტრაცია</a>
    <?php else: ?>
      <a href="/sport_blog/views/notifications" class="sport-navbar__auth-button sport-navbar__auth-button--login">შეტყობინებები<?= $headerUnreadCount ? ' (' . $headerUnreadCount . ')' : '' ?></a>
      <a href="/sport_blog/views/profile" class="sport-navbar__auth-button sport-navbar__auth-button--login">პროფილი</a>
      <form action="/sport_blog/auth_system/logout" method="post">
        <?php render_logout_token(); ?>
        <button type="submit" class="sport-navbar__auth-button sport-navbar__auth-button--register">გასვლა</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<script>
  (() => {
    const hamburger = document.querySelector('.sport-navbar__hamburger-icon');
    const mobileMenu = document.querySelector('.sport-navbar__mobile-menu');
    const closeButton = document.querySelector('.sport-navbar__mobile-close');
    const navbar = document.querySelector('.sport-navbar');

    if (hamburger && mobileMenu) {
      const setOpen = (isOpen) => {
        mobileMenu.classList.toggle('sport-navbar__mobile-menu--active', isOpen);
        hamburger.setAttribute('aria-expanded', String(isOpen));
        mobileMenu.setAttribute('aria-hidden', String(!isOpen));
        document.body.classList.toggle('no-scroll', isOpen);
      };

      hamburger.addEventListener('click', () => setOpen(!mobileMenu.classList.contains('sport-navbar__mobile-menu--active')));
      closeButton?.addEventListener('click', () => setOpen(false));
      mobileMenu.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setOpen(false)));
      document.addEventListener('keydown', (event) => { if (event.key === 'Escape') setOpen(false); });
    }

    // გადახვევისას header კომპაქტური ხდება
    if (navbar) {
      const onScroll = () => navbar.classList.toggle('sport-navbar--scrolled', window.scrollY > 8);
      onScroll();
      window.addEventListener('scroll', onScroll, { passive: true });
    }

    // აქტიური კატეგორია ხილულ არეში
    document.querySelector('.sport-navbar__sports-item--active')?.scrollIntoView({ block: 'nearest', inline: 'center' });

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
      button.addEventListener('click', () => {
        const root = document.documentElement;
        const next = root.dataset.theme === 'light' ? 'dark' : 'light';
        root.dataset.theme = next;
        try { localStorage.setItem('sb-theme', next); } catch (e) {}
      });
    });
  })();
</script>
