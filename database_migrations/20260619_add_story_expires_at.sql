ALTER TABLE articles
    ADD COLUMN IF NOT EXISTS story_expires_at DATETIME NULL AFTER show_in_story;
