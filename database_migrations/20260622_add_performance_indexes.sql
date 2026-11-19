UPDATE matches m
JOIN (
    SELECT id, slug, ROW_NUMBER() OVER (PARTITION BY slug ORDER BY id) AS row_number
    FROM matches
    WHERE slug IS NOT NULL AND slug <> ''
) ranked ON ranked.id = m.id
SET m.slug = CONCAT(m.slug, '-', m.id)
WHERE ranked.row_number > 1;

ALTER TABLE matches ADD UNIQUE INDEX uq_matches_slug (slug);
ALTER TABLE articles ADD INDEX idx_articles_status_created (status, created_at);
ALTER TABLE articles ADD INDEX idx_articles_category_status_created (category, status, created_at);
ALTER TABLE sports_events ADD INDEX idx_sports_events_type_date (sport_type, event_date, event_time);
