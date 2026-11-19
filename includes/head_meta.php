<?php /* საერთო <head> ელემენტები: თემა, PWA manifest, ხატულები, RSS */ ?>
    <script>
      // თემა: შენახული არჩევანი, თუ არა — სისტემის (ტელეფონის/კომპიუტერის) ღია/მუქი რეჟიმი
      (() => {
        const root = document.documentElement;
        const media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: light)') : null;
        let saved = null;
        try { saved = localStorage.getItem('sb-theme'); } catch (e) {}
        const apply = () => {
          root.dataset.theme = saved === 'light' || saved === 'dark' ? saved : (media && media.matches ? 'light' : 'dark');
        };
        apply();
        if (media && media.addEventListener) {
          media.addEventListener('change', () => {
            try { saved = localStorage.getItem('sb-theme'); } catch (e) {}
            if (saved !== 'light' && saved !== 'dark') apply();
          });
        }
      })();
    </script>
    <meta name="color-scheme" content="dark light">
    <meta name="theme-color" content="#0a0d12" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#f2f4f7" media="(prefers-color-scheme: light)">
    <link rel="manifest" href="/sport_blog/manifest.webmanifest">
    <link rel="icon" type="image/svg+xml" href="/sport_blog/assets/brand/app-icon.svg">
    <link rel="icon" type="image/png" sizes="192x192" href="/sport_blog/assets/brand/app-icon-192.png">
    <link rel="apple-touch-icon" href="/sport_blog/assets/brand/apple-touch-icon.png">
    <link rel="alternate" type="application/rss+xml" title="Sport Blog" href="/sport_blog/feed.xml">
