ALTER TABLE articles
    ADD COLUMN IF NOT EXISTS show_in_story TINYINT(1) NOT NULL DEFAULT 0 AFTER story_image;

UPDATE articles
SET show_in_story = 1
WHERE story_image IS NOT NULL AND story_image != '';
