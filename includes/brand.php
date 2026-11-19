<?php

/** Sport Blog-ის ლოგო inline SVG-ით: ტექსტი იყენებს currentColor-ს, ამიტომ ორივე თემაზე ჩანს. */
function sb_render_logo(string $href = '/sport_blog/'): void
{
    ?>
    <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="sb-logo" aria-label="Sport Blog მთავარი გვერდი">
      <svg class="sb-logo__mark" viewBox="0 0 40 40" aria-hidden="true" focusable="false">
        <rect width="40" height="40" rx="11" fill="#c8ff2e"/>
        <path d="M11 26.5c3.4 2.6 9.8 2.9 12.6-.4 2.4-2.9-.2-5.4-4.3-6.4-4.2-1-6.3-2.6-4.5-5.2 2-2.8 7.7-2.4 10.6-.2" fill="none" stroke="#0a0d12" stroke-width="3.6" stroke-linecap="round"/>
        <circle cx="29.5" cy="27.5" r="3.2" fill="#0a0d12"/>
      </svg>
      <span class="sb-logo__word">Sport<b>Blog</b></span>
    </a>
    <?php
}

function sb_render_theme_toggle(): void
{
    ?>
    <button type="button" class="sb-theme-toggle" data-theme-toggle aria-label="თემის შეცვლა" title="მუქი / ღია თემა">
      <i class="fas fa-moon sb-theme-toggle__moon" aria-hidden="true"></i>
      <i class="fas fa-sun sb-theme-toggle__sun" aria-hidden="true"></i>
    </button>
    <?php
}
