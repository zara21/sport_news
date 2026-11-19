<!-- stories.php -->
<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/media_helpers.php';
require_once __DIR__ . '/../includes/cache_helpers.php';
?>

<section class="story-feed__container" aria-label="კლუბების ისტორიები">
  <?php
  $storyClubs = cache_remember('home.story-clubs.v2', 90, function () use ($pdo): array {
      $stmt = $pdo->query("
          SELECT DISTINCT c.club_name, c.logo_url
          FROM club_logos c
          INNER JOIN tags t ON t.name = c.club_name
          INNER JOIN article_tags at ON at.tag_id = t.id
          INNER JOIN articles a ON a.id = at.article_id
          WHERE a.status = 'published'
            AND a.show_in_story = 1
            AND (a.story_expires_at IS NULL OR a.story_expires_at > NOW())
          ORDER BY c.club_name ASC
          LIMIT 30
      ");
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
  });
  if (!$storyClubs) {
      echo '<div class="story-feed__empty">სთორი ჯერ არ არის დამატებული.</div>';
  }
  foreach ($storyClubs as $storyIndex => $row) {
      $club = htmlspecialchars($row['club_name']);
      $logo = htmlspecialchars(cached_remote_image_url($row['logo_url'] ?: SPORT_BLOG_DEFAULT_IMAGE));
      $loading = $storyIndex < 8 ? 'eager' : 'lazy';
      echo "
      <button class=\"story-feed__item story-feed__item--unseen\" type=\"button\" data-club=\"$club\" aria-label=\"$club\">
          <span class=\"story-feed__item-inner\">
              <img src=\"$logo\" alt=\"$club\" class=\"story-feed__logo\" width=\"72\" height=\"72\" loading=\"$loading\" decoding=\"async\" onerror=\"this.src='" . SPORT_BLOG_DEFAULT_IMAGE . "'\">
          </span>
      </button>";
  }
  ?>
</section>

<div class="story-viewer__overlay" id="storyOverlay">
  <div class="story-viewer__popup">
    <button class="story-viewer__close-btn" type="button" onclick="storyViewer.close()" aria-label="დახურვა">&times;</button>
    <button class="story-viewer__nav-btn left" type="button" onclick="storyViewer.previousClub()" aria-label="წინა">&#8249;</button>
    <button class="story-viewer__nav-btn right" type="button" onclick="storyViewer.nextClub()" aria-label="შემდეგი">&#8250;</button>
    <div class="story-viewer__progress-container" id="progressContainer"></div>
    <div id="story-content"></div>
  </div>
</div>

<script>
  class StoryViewer {
    constructor() {
      this.clubElements = Array.from(document.querySelectorAll('.story-feed__item'));
      this.currentClubIndex = 0;
      this.currentIndex = 0;
      this.stories = [];
      this.currentClubLogo = '';
      this.timer = null;
      this.startedAt = 0;
      this.remaining = 5000;
      this.duration = 5000;
      this.touchStartX = 0;
      this.seenClubs = new Set(JSON.parse(localStorage.getItem('sportBlogSeenStories') || '[]'));
      this.applySeenState();
      this.bindViewerGestures();
    }

    applySeenState() {
      this.clubElements.forEach((item) => {
        const club = item.dataset.club;
        item.classList.toggle('story-feed__item--seen', this.seenClubs.has(club));
        item.classList.toggle('story-feed__item--unseen', !this.seenClubs.has(club));
      });
    }

    markSeen(club) {
      this.seenClubs.add(club);
      localStorage.setItem('sportBlogSeenStories', JSON.stringify(Array.from(this.seenClubs)));
      this.applySeenState();
    }

    bindViewerGestures() {
      const popup = document.querySelector('.story-viewer__popup');
      if (!popup) return;
      popup.addEventListener('mouseenter', () => this.pauseTimer());
      popup.addEventListener('mouseleave', () => this.resumeTimer());
      popup.addEventListener('touchstart', (event) => {
        this.touchStartX = event.touches[0].clientX;
        this.pauseTimer();
      }, { passive: true });
      popup.addEventListener('touchend', (event) => {
        const diff = event.changedTouches[0].clientX - this.touchStartX;
        if (Math.abs(diff) > 45) {
          diff < 0 ? this.next() : this.previous();
        } else {
          this.resumeTimer();
        }
      }, { passive: true });
    }

    async openByIndex(index) {
      if (index < 0 || index >= this.clubElements.length) return;
      this.currentClubIndex = index;
      const club = this.clubElements[this.currentClubIndex].dataset.club;
      await this.open(club);
    }

    async open(club) {
      try {
        const res = await fetch(`/sport_blog/views/fetch_story.php?club=${encodeURIComponent(club)}`);
        const data = await res.json();
        this.stories = Array.isArray(data.stories) ? data.stories.slice(0, 5) : [];
        if (!this.stories.length) return;

        this.currentClubLogo = data.club_logo || '';
        this.currentIndex = 0;
        this.render(data);
        document.getElementById('storyOverlay').style.display = 'flex';
        document.body.classList.add('no-scroll');
        this.markSeen(club);
        this.startTimer();
      } catch (err) {
        console.error('Failed to load story', err);
      }
    }

    render(data) {
      const story = this.stories[this.currentIndex];
      const container = document.getElementById('story-content');
      if (!story || !container) return;

      const escapeHtml = (value) => String(value || '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
      })[character]);
      const safeInternalLink = String(story.link || '').startsWith('/sport_blog/') ? story.link : '#';
      const safeImage = /^(https?:\/\/|\/sport_blog\/)/i.test(String(story.image_url || ''))
        ? story.image_url
        : '<?= SPORT_BLOG_DEFAULT_IMAGE ?>';
      const clubLogo = /^(https?:\/\/|\/sport_blog\/)/i.test(String(data.club_logo || this.currentClubLogo || ''))
        ? (data.club_logo || this.currentClubLogo)
        : '<?= SPORT_BLOG_DEFAULT_IMAGE ?>';
      const clubName = data.club_name || this.clubElements[this.currentClubIndex].dataset.club;

      container.innerHTML = `
        <a href="${escapeHtml(safeInternalLink)}" class="story-link">
          <div class="story-viewer__image-wrapper">
            <img src="${escapeHtml(safeImage)}" class="story-viewer__image ${story.image_fit === 'contain' ? 'story-viewer__image--contain' : ''}" alt="${escapeHtml(story.title)}" width="720" height="1280" onerror="this.src='<?= SPORT_BLOG_DEFAULT_IMAGE ?>'" />
            <div class="story-viewer__header-area">
              <img src="${escapeHtml(clubLogo)}" class="story-viewer__logo" alt="" width="42" height="42" />
              <span>${escapeHtml(clubName)}</span>
            </div>
            <div class="story-viewer__footer-area">
              <div class="story-viewer__title">${escapeHtml(story.title)}</div>
              <div class="story-viewer__timestamp">${escapeHtml(story.date ? new Date(story.date).toLocaleDateString('ka-GE') : '')}</div>
            </div>
          </div>
        </a>
      `;
      this.renderProgressBar();
    }

    renderProgressBar() {
      const container = document.getElementById('progressContainer');
      container.innerHTML = this.stories.map((_, i) => `
        <div class="story-viewer__progress-segment ${i === this.currentIndex ? 'story-viewer__progress-segment--active' : ''}"></div>
      `).join('');
    }

    startTimer() {
      clearTimeout(this.timer);
      this.remaining = this.duration;
      this.startedAt = Date.now();
      document.querySelector('.story-viewer__progress-segment--active')?.classList.remove('story-viewer__progress-segment--paused');
      this.timer = setTimeout(() => this.next(), this.remaining);
    }

    pauseTimer() {
      if (!this.timer) return;
      clearTimeout(this.timer);
      this.timer = null;
      this.remaining = Math.max(250, this.remaining - (Date.now() - this.startedAt));
      document.querySelector('.story-viewer__progress-segment--active')?.classList.add('story-viewer__progress-segment--paused');
    }

    resumeTimer() {
      if (this.timer || document.getElementById('storyOverlay').style.display !== 'flex') return;
      this.startedAt = Date.now();
      document.querySelector('.story-viewer__progress-segment--active')?.classList.remove('story-viewer__progress-segment--paused');
      this.timer = setTimeout(() => this.next(), this.remaining);
    }

    next() {
      if (this.currentIndex < this.stories.length - 1) {
        this.currentIndex++;
        this.render({ club_name: this.clubElements[this.currentClubIndex].dataset.club, club_logo: this.currentClubLogo });
        this.startTimer();
      } else {
        this.nextClub();
      }
    }

    previous() {
      if (this.currentIndex > 0) {
        this.currentIndex--;
        this.render({ club_name: this.clubElements[this.currentClubIndex].dataset.club, club_logo: this.currentClubLogo });
        this.startTimer();
      }
    }

    nextClub() {
      if (this.currentClubIndex < this.clubElements.length - 1) {
        this.openByIndex(this.currentClubIndex + 1);
      } else {
        this.close();
      }
    }

    previousClub() {
      if (this.currentClubIndex > 0) {
        this.openByIndex(this.currentClubIndex - 1);
      }
    }

    close() {
      clearTimeout(this.timer);
      document.getElementById('storyOverlay').style.display = 'none';
      document.body.classList.remove('no-scroll');
    }
  }

  const storyViewer = new StoryViewer();
  document.querySelectorAll('.story-feed__item').forEach((item, index) => {
    item.addEventListener('click', () => storyViewer.openByIndex(index));
  });
</script>
