<?php require_once __DIR__ . '/brand.php'; ?>
<footer class="site-footer">
  <div class="site-footer__main">
    <div class="site-footer__content-wrapper">
      <div class="site-footer__brand-section">
        <?php sb_render_logo(); ?>
        <p class="site-footer__description">
          სპორტის უახლესი სიახლეები, მატჩები, ცხრილები, ვიდეოები და ქვიზები — ერთ სივრცეში.
        </p>
      </div>

      <nav class="site-footer__nav-section" aria-label="კატეგორიები">
        <h4 class="site-footer__nav-title">კატეგორიები</h4>
        <ul class="site-footer__nav-list">
          <li class="site-footer__nav-item"><a href="/sport_blog/category/football" class="site-footer__nav-link">ფეხბურთი</a></li>
          <li class="site-footer__nav-item"><a href="/sport_blog/category/basketball" class="site-footer__nav-link">კალათბურთი</a></li>
          <li class="site-footer__nav-item"><a href="/sport_blog/category/tennis" class="site-footer__nav-link">ჩოგბურთი</a></li>
          <li class="site-footer__nav-item"><a href="/sport_blog/category/formula1" class="site-footer__nav-link">ფორმულა 1</a></li>
          <li class="site-footer__nav-item"><a href="/sport_blog/category/mma" class="site-footer__nav-link">MMA</a></li>
          <li class="site-footer__nav-item"><a href="/sport_blog/category/rugby" class="site-footer__nav-link">რაგბი</a></li>
        </ul>
      </nav>

      <nav class="site-footer__nav-section" aria-label="სწრაფი ბმულები">
        <h4 class="site-footer__nav-title">სწრაფი ბმულები</h4>
        <ul class="site-footer__nav-list">
          <li class="site-footer__nav-item"><a href="/sport_blog/views/articles" class="site-footer__nav-link">ყველა სტატია</a></li>
          <li class="site-footer__nav-item"><a href="/sport_blog/views/all_quizzes" class="site-footer__nav-link">ქვიზები</a></li>
          <li class="site-footer__nav-item"><a href="/sport_blog/#video-news" class="site-footer__nav-link">ვიდეოები</a></li>
          <li class="site-footer__nav-item"><a href="/sport_blog/#standings" class="site-footer__nav-link">ცხრილები</a></li>
        </ul>
      </nav>

      <nav class="site-footer__nav-section" aria-label="ანგარიში">
        <h4 class="site-footer__nav-title">ანგარიში</h4>
        <ul class="site-footer__nav-list">
          <?php if (!empty($_SESSION['user_id'])): ?>
            <li class="site-footer__nav-item"><a href="/sport_blog/views/profile" class="site-footer__nav-link">პროფილი</a></li>
            <li class="site-footer__nav-item"><a href="/sport_blog/views/library" class="site-footer__nav-link">ჩემი ბიბლიოთეკა</a></li>
            <li class="site-footer__nav-item"><a href="/sport_blog/views/notifications" class="site-footer__nav-link">შეტყობინებები</a></li>
          <?php else: ?>
            <li class="site-footer__nav-item"><a href="/sport_blog/auth_system/login" class="site-footer__nav-link">შესვლა</a></li>
            <li class="site-footer__nav-item"><a href="/sport_blog/auth_system/register" class="site-footer__nav-link">რეგისტრაცია</a></li>
          <?php endif; ?>
        </ul>
      </nav>
    </div>
  </div>

  <div class="site-footer__copyright">
    <span>&copy; <?= date('Y') ?> SportBlog. ყველა უფლება დაცულია.</span>
    <a href="#" class="site-footer__top" onclick="window.scrollTo({top:0});return false;">ზემოთ <i class="fas fa-arrow-up"></i></a>
  </div>
</footer>
