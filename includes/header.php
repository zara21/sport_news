<!-- header.php -->
<?php
if (!empty($_SESSION['user_id']) && isset($pdo) && $pdo instanceof PDO) {
    require_once __DIR__ . '/user_activity_helpers.php';
    $headerUnreadCount = unread_notification_count($pdo, (int) $_SESSION['user_id']);
} else {
    $headerUnreadCount = 0;
}
?>
<nav class="sport-navbar" aria-label="მთავარი ნავიგაცია">
  <div class="sport-navbar__logo-wrapper">
    <a href="/sport_blog/" aria-label="Sport Blog მთავარი გვერდი">
      <img src="/sport_blog/assets/brand/sportblog-logo.svg" alt="Sport Blog" width="140" height="44">
    </a>
  </div>

  <div class="sport-navbar__center">
    <div class="sport-navbar__sports-inline">
      <a href="/sport_blog/category/football" class="sport-navbar__sports-item"><i class="fas fa-futbol"></i> ფეხბურთი</a>
      <a href="/sport_blog/category/basketball" class="sport-navbar__sports-item"><i class="fas fa-basketball-ball"></i> კალათბურთი</a>
      <a href="/sport_blog/category/tennis" class="sport-navbar__sports-item"><i class="fas fa-table-tennis"></i> ჩოგბურთი</a>
      <a href="/sport_blog/category/formula1" class="sport-navbar__sports-item"><i class="fas fa-flag-checkered"></i> ფორმულა 1</a>
      <a href="/sport_blog/category/mma" class="sport-navbar__sports-item"><i class="fas fa-hand-fist"></i> MMA</a>
      <a href="/sport_blog/category/rugby" class="sport-navbar__sports-item"><i class="fas fa-football-ball"></i> რაგბი</a>
      <a href="/sport_blog/category/other" class="sport-navbar__sports-item"><i class="fas fa-ellipsis-h"></i> სხვა</a>
    </div>

    <a href="/sport_blog/views/all_quizzes" class="sport-navbar__main-link">ქვიზები</a>
    <a href="/sport_blog/#video-news" class="sport-navbar__main-link">ვიდეოები</a>
    <a href="/sport_blog/#standings" class="sport-navbar__main-link">ცხრილები</a>
  </div>

  <div class="sport-navbar__auth">
    <?php if (!isset($_SESSION['user'])): ?>
      <a href="/sport_blog/auth_system/login" class="sport-navbar__auth-button sport-navbar__auth-button--login">შესვლა</a>
      <a href="/sport_blog/auth_system/register" class="sport-navbar__auth-button sport-navbar__auth-button--register">რეგისტრაცია</a>
    <?php else: ?>
      <a href="/sport_blog/views/notifications" class="sport-navbar__notification" aria-label="შეტყობინებები"><i class="fas fa-bell"></i><?php if ($headerUnreadCount): ?><span><?= min(99, $headerUnreadCount) ?></span><?php endif; ?></a>
      <a href="/sport_blog/views/profile" class="sport-navbar__auth-button sport-navbar__auth-button--login"><i class="fas fa-user"></i> პროფილი</a>
      <a href="/sport_blog/auth_system/logout" class="sport-navbar__auth-button sport-navbar__auth-button--register"><i class="fas fa-sign-out-alt"></i> გასვლა</a>
    <?php endif; ?>
  </div>

  <button class="sport-navbar__hamburger-icon" type="button" aria-label="მენიუს გახსნა" aria-expanded="false">
    <i class="fas fa-bars"></i>
  </button>

  <div class="sport-navbar__mobile-menu" id="mobileMenu" aria-hidden="true">
    <div class="sport-navbar__mobile-head">
      <img src="/sport_blog/assets/brand/sportblog-logo.svg" alt="Sport Blog" width="140" height="44">
      <button class="sport-navbar__mobile-close" type="button" aria-label="მენიუს დახურვა">&times;</button>
    </div>

    <div class="sport-navbar__mobile-section">
      <span class="sport-navbar__mobile-title">კატეგორიები</span>
      <div class="sport-navbar__mobile-grid">
        <a href="/sport_blog/category/football" class="sport-navbar__main-link"><i class="fas fa-futbol"></i> ფეხბურთი</a>
        <a href="/sport_blog/category/basketball" class="sport-navbar__main-link"><i class="fas fa-basketball-ball"></i> კალათბურთი</a>
        <a href="/sport_blog/category/tennis" class="sport-navbar__main-link"><i class="fas fa-table-tennis"></i> ჩოგბურთი</a>
        <a href="/sport_blog/category/formula1" class="sport-navbar__main-link"><i class="fas fa-flag-checkered"></i> ფორმულა 1</a>
        <a href="/sport_blog/category/mma" class="sport-navbar__main-link"><i class="fas fa-hand-fist"></i> MMA</a>
        <a href="/sport_blog/category/rugby" class="sport-navbar__main-link"><i class="fas fa-football-ball"></i> რაგბი</a>
        <a href="/sport_blog/category/other" class="sport-navbar__main-link"><i class="fas fa-ellipsis-h"></i> სხვა</a>
      </div>
    </div>

    <div class="sport-navbar__mobile-section">
      <span class="sport-navbar__mobile-title">სწრაფი ბმულები</span>
      <div class="sport-navbar__mobile-grid">
        <a href="/sport_blog/views/all_quizzes" class="sport-navbar__main-link">ქვიზები</a>
        <a href="/sport_blog/#video-news" class="sport-navbar__main-link">ვიდეოები</a>
        <a href="/sport_blog/#standings" class="sport-navbar__main-link">ცხრილები</a>
      </div>
    </div>

    <div class="sport-navbar__mobile-auth">
      <?php if (!isset($_SESSION['user'])): ?>
        <a href="/sport_blog/auth_system/login" class="sport-navbar__auth-button sport-navbar__auth-button--login">შესვლა</a>
        <a href="/sport_blog/auth_system/register" class="sport-navbar__auth-button sport-navbar__auth-button--register">რეგისტრაცია</a>
      <?php else: ?>
        <a href="/sport_blog/views/notifications" class="sport-navbar__main-link">შეტყობინებები<?= $headerUnreadCount ? ' (' . $headerUnreadCount . ')' : '' ?></a>
        <a href="/sport_blog/views/profile" class="sport-navbar__auth-button sport-navbar__auth-button--login">პროფილი</a>
        <a href="/sport_blog/auth_system/logout" class="sport-navbar__auth-button sport-navbar__auth-button--register">გასვლა</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<script>
  (() => {
    const hamburger = document.querySelector('.sport-navbar__hamburger-icon');
    const mobileMenu = document.querySelector('.sport-navbar__mobile-menu');
    const closeButton = document.querySelector('.sport-navbar__mobile-close');

    if (!hamburger || !mobileMenu) return;

    const setOpen = (isOpen) => {
      mobileMenu.classList.toggle('sport-navbar__mobile-menu--active', isOpen);
      hamburger.setAttribute('aria-expanded', String(isOpen));
      mobileMenu.setAttribute('aria-hidden', String(!isOpen));
      document.body.classList.toggle('no-scroll', isOpen);
    };

    hamburger.addEventListener('click', () => setOpen(!mobileMenu.classList.contains('sport-navbar__mobile-menu--active')));
    closeButton?.addEventListener('click', () => setOpen(false));
    mobileMenu.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setOpen(false)));
  })();
</script>
