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

<div class="story-viewer__overlay" id="storyOverlay" aria-hidden="true">
  <div class="story-viewer__backdrop" id="storyBackdrop"></div>

  <button class="story-viewer__club-nav story-viewer__club-nav--prev" type="button" data-story-action="prev-club" aria-label="წინა კლუბი">
    <i class="fas fa-chevron-left" aria-hidden="true"></i>
  </button>

  <div class="story-viewer__popup" role="dialog" aria-modal="true" aria-label="სთორი">
    <div class="story-viewer__progress-container" id="progressContainer"></div>

    <div class="story-viewer__header">
      <img src="" class="story-viewer__logo" id="storyClubLogo" alt="" width="40" height="40">
      <div class="story-viewer__club">
        <strong id="storyClubName"></strong>
        <span id="storyTime"></span>
      </div>
      <button class="story-viewer__icon-btn" type="button" data-story-action="toggle-pause" aria-label="პაუზა" id="storyPauseBtn">
        <i class="fas fa-pause" aria-hidden="true"></i>
      </button>
      <button class="story-viewer__icon-btn" type="button" data-story-action="close" aria-label="დახურვა">
        <i class="fas fa-xmark" aria-hidden="true"></i>
      </button>
    </div>

    <div class="story-viewer__media" id="storyMedia"></div>

    <button class="story-viewer__tap story-viewer__tap--prev" type="button" data-story-tap="prev" aria-label="წინა სთორი"></button>
    <button class="story-viewer__tap story-viewer__tap--next" type="button" data-story-tap="next" aria-label="შემდეგი სთორი"></button>

    <div class="story-viewer__footer" id="storyFooter"></div>
  </div>

  <button class="story-viewer__club-nav story-viewer__club-nav--next" type="button" data-story-action="next-club" aria-label="შემდეგი კლუბი">
    <i class="fas fa-chevron-right" aria-hidden="true"></i>
  </button>
</div>

<script>
  class StoryViewer {
    constructor() {
      this.overlay = document.getElementById('storyOverlay');
      this.clubElements = Array.from(document.querySelectorAll('.story-feed__item'));
      this.currentClubIndex = 0;
      this.currentIndex = 0;
      this.stories = [];
      this.clubName = '';
      this.clubLogo = '';
      this.duration = 6000;
      this.remaining = this.duration;
      this.startedAt = 0;
      this.timer = null;
      this.paused = false;
      this.holdTimer = null;
      this.held = false;
      this.defaultImage = '<?= SPORT_BLOG_DEFAULT_IMAGE ?>';
      try {
        this.seenClubs = new Set(JSON.parse(localStorage.getItem('sportBlogSeenStories') || '[]'));
      } catch (e) {
        this.seenClubs = new Set();
      }
      this.applySeenState();
      this.bindEvents();
    }

    escapeHtml(value) {
      return String(value ?? '').replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch]);
    }

    safeUrl(value, fallback) {
      return /^(https?:\/\/|\/sport_blog\/)/i.test(String(value || '')) ? value : fallback;
    }

    relativeTime(value) {
      const date = new Date(String(value || '').replace(' ', 'T'));
      if (Number.isNaN(date.getTime())) return '';
      const minutes = Math.round((Date.now() - date.getTime()) / 60000);
      try {
        const rtf = new Intl.RelativeTimeFormat('ka', { numeric: 'auto' });
        if (minutes < 60) return rtf.format(-Math.max(1, minutes), 'minute');
        if (minutes < 1440) return rtf.format(-Math.round(minutes / 60), 'hour');
        if (minutes < 10080) return rtf.format(-Math.round(minutes / 1440), 'day');
      } catch (e) {}
      return date.toLocaleDateString('ka-GE');
    }

    applySeenState() {
      this.clubElements.forEach((item) => {
        const seen = this.seenClubs.has(item.dataset.club);
        item.classList.toggle('story-feed__item--seen', seen);
        item.classList.toggle('story-feed__item--unseen', !seen);
      });
    }

    markSeen(club) {
      this.seenClubs.add(club);
      try {
        localStorage.setItem('sportBlogSeenStories', JSON.stringify(Array.from(this.seenClubs)));
      } catch (e) {}
      this.applySeenState();
    }

    bindEvents() {
      this.overlay.addEventListener('click', (event) => {
        const action = event.target.closest('[data-story-action]')?.dataset.storyAction;
        if (action === 'close') this.close();
        if (action === 'prev-club') this.previousClub();
        if (action === 'next-club') this.nextClub();
        if (action === 'toggle-pause') this.paused ? this.resume(true) : this.pause(true);
        if (event.target === this.overlay || event.target.id === 'storyBackdrop') this.close();
      });

      // შეხება: მოკლე დაჭერა = წინა/შემდეგი, ხანგრძლივი დაჭერა = პაუზა (ისე, როგორც სოციალურ ქსელებში)
      this.overlay.querySelectorAll('[data-story-tap]').forEach((zone) => {
        zone.addEventListener('pointerdown', () => {
          this.held = false;
          this.holdTimer = setTimeout(() => { this.held = true; this.pause(); }, 220);
        });
        const release = () => {
          clearTimeout(this.holdTimer);
          if (this.held) { this.held = false; this.resume(); return true; }
          return false;
        };
        zone.addEventListener('pointerup', () => {
          if (release()) return;
          zone.dataset.storyTap === 'next' ? this.next() : this.previous();
        });
        zone.addEventListener('pointerleave', release);
        zone.addEventListener('contextmenu', (event) => event.preventDefault());
      });

      let touchX = 0;
      const popup = this.overlay.querySelector('.story-viewer__popup');
      popup.addEventListener('touchstart', (event) => { touchX = event.touches[0].clientX; }, { passive: true });
      popup.addEventListener('touchend', (event) => {
        const diff = event.changedTouches[0].clientX - touchX;
        if (Math.abs(diff) > 60) diff < 0 ? this.nextClub() : this.previousClub();
      }, { passive: true });

      document.addEventListener('keydown', (event) => {
        if (!this.isOpen()) return;
        if (event.key === 'Escape') this.close();
        if (event.key === 'ArrowRight') this.next();
        if (event.key === 'ArrowLeft') this.previous();
        if (event.key === ' ') { event.preventDefault(); this.paused ? this.resume(true) : this.pause(true); }
      });

      document.addEventListener('visibilitychange', () => {
        if (!this.isOpen()) return;
        document.hidden ? this.pause() : this.resume();
      });
    }

    isOpen() {
      return this.overlay.classList.contains('is-open');
    }

    async openByIndex(index) {
      if (index < 0 || index >= this.clubElements.length) return;
      this.currentClubIndex = index;
      await this.open(this.clubElements[index].dataset.club);
    }

    async open(club) {
      try {
        const res = await fetch(`/sport_blog/views/fetch_story.php?club=${encodeURIComponent(club)}`);
        const data = await res.json();
        const stories = Array.isArray(data.stories) ? data.stories.slice(0, 5) : [];
        if (!stories.length) return;

        this.stories = stories;
        this.clubName = data.club_name || club;
        this.clubLogo = this.safeUrl(data.club_logo, this.defaultImage);
        this.currentIndex = 0;
        this.overlay.classList.add('is-open');
        this.overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('no-scroll');
        this.updateClubNav();
        this.render();
        this.markSeen(club);
      } catch (err) {
        console.error('Failed to load story', err);
      }
    }

    updateClubNav() {
      this.overlay.querySelector('.story-viewer__club-nav--prev').disabled = this.currentClubIndex <= 0;
      this.overlay.querySelector('.story-viewer__club-nav--next').disabled = this.currentClubIndex >= this.clubElements.length - 1;
    }

    render() {
      const story = this.stories[this.currentIndex];
      if (!story) return;

      const image = this.safeUrl(story.image_url, this.defaultImage);
      const link = String(story.link || '').startsWith('/sport_blog/') ? story.link : '#';
      const contain = story.image_fit === 'contain';

      document.getElementById('storyClubLogo').src = this.clubLogo;
      document.getElementById('storyClubName').textContent = this.clubName;
      document.getElementById('storyTime').textContent = this.relativeTime(story.date);
      document.getElementById('storyBackdrop').style.backgroundImage = `url("${encodeURI(image)}")`;

      document.getElementById('storyMedia').innerHTML = `
        <img src="${this.escapeHtml(image)}" class="story-viewer__image ${contain ? 'story-viewer__image--contain' : ''}"
             alt="" width="720" height="1280" onerror="this.onerror=null;this.src='${this.defaultImage}'">`;

      document.getElementById('storyFooter').innerHTML = `
        ${story.category ? `<span class="story-viewer__category">${this.escapeHtml(story.category)}</span>` : ''}
        <h3 class="story-viewer__title"><a href="${this.escapeHtml(link)}">${this.escapeHtml(story.title)}</a></h3>`;

      this.renderProgressBar();
      this.paused = false;
      this.updatePauseButton();
      this.startTimer();
    }

    renderProgressBar() {
      const container = document.getElementById('progressContainer');
      container.style.setProperty('--story-duration', `${this.duration}ms`);
      container.innerHTML = this.stories.map((_, i) => {
        const state = i < this.currentIndex ? 'done' : (i === this.currentIndex ? 'active' : '');
        return `<div class="story-viewer__progress-segment ${state ? 'story-viewer__progress-segment--' + state : ''}"><i></i></div>`;
      }).join('');
    }

    activeSegment() {
      return document.querySelector('.story-viewer__progress-segment--active');
    }

    startTimer() {
      clearTimeout(this.timer);
      this.remaining = this.duration;
      this.startedAt = Date.now();
      this.timer = setTimeout(() => this.next(), this.remaining);
    }

    pause(manual = false) {
      if (manual) { this.paused = true; this.updatePauseButton(); }
      if (!this.timer) return;
      clearTimeout(this.timer);
      this.timer = null;
      this.remaining = Math.max(200, this.remaining - (Date.now() - this.startedAt));
      this.activeSegment()?.classList.add('story-viewer__progress-segment--paused');
    }

    resume(manual = false) {
      if (manual) { this.paused = false; this.updatePauseButton(); }
      if (this.paused || this.timer || !this.isOpen()) return;
      this.startedAt = Date.now();
      this.activeSegment()?.classList.remove('story-viewer__progress-segment--paused');
      this.timer = setTimeout(() => this.next(), this.remaining);
    }

    updatePauseButton() {
      const button = document.getElementById('storyPauseBtn');
      button.innerHTML = `<i class="fas ${this.paused ? 'fa-play' : 'fa-pause'}" aria-hidden="true"></i>`;
      button.setAttribute('aria-label', this.paused ? 'გაგრძელება' : 'პაუზა');
    }

    next() {
      if (this.currentIndex < this.stories.length - 1) {
        this.currentIndex++;
        this.render();
      } else {
        this.nextClub();
      }
    }

    previous() {
      if (this.currentIndex > 0) {
        this.currentIndex--;
        this.render();
      } else if (this.currentClubIndex > 0) {
        this.previousClub();
      } else {
        this.render();
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
      if (this.currentClubIndex > 0) this.openByIndex(this.currentClubIndex - 1);
    }

    close() {
      clearTimeout(this.timer);
      this.timer = null;
      this.overlay.classList.remove('is-open');
      this.overlay.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('no-scroll');
    }
  }

  const storyViewer = new StoryViewer();
  document.querySelectorAll('.story-feed__item').forEach((item, index) => {
    item.addEventListener('click', () => storyViewer.openByIndex(index));
  });
</script>
