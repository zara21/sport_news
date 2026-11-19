CREATE TABLE IF NOT EXISTS article_bookmarks (
    user_id INT NOT NULL,
    article_id INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, article_id),
    KEY idx_bookmarks_created (user_id, created_at),
    CONSTRAINT fk_bookmark_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_bookmark_article FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS article_reads (
    user_id INT NOT NULL,
    article_id INT NOT NULL,
    read_count INT NOT NULL DEFAULT 1,
    last_read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, article_id),
    KEY idx_reads_recent (user_id, last_read_at),
    CONSTRAINT fk_read_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_read_article FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tag_aliases (
    id INT NOT NULL AUTO_INCREMENT,
    tag_id INT NOT NULL,
    alias VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tag_alias (alias),
    KEY idx_tag_alias_tag (tag_id),
    CONSTRAINT fk_tag_alias_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    link VARCHAR(1024) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notifications_user_created (user_id, created_at),
    CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notification_reads (
    notification_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (notification_id, user_id),
    CONSTRAINT fk_notification_read_notification FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
    CONSTRAINT fk_notification_read_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS story_clicks (
    id BIGINT NOT NULL AUTO_INCREMENT,
    article_id INT NOT NULL,
    user_id INT NULL,
    session_key VARCHAR(128) DEFAULT NULL,
    clicked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_story_click_article_date (article_id, clicked_at),
    CONSTRAINT fk_story_click_article FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    CONSTRAINT fk_story_click_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS data_sync_runs (
    id BIGINT NOT NULL AUTO_INCREMENT,
    sync_type VARCHAR(50) NOT NULL,
    status ENUM('running','success','failed') NOT NULL DEFAULT 'running',
    rows_changed INT NOT NULL DEFAULT 0,
    message TEXT DEFAULT NULL,
    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_sync_type_started (sync_type, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE articles ADD COLUMN IF NOT EXISTS scheduled_at DATETIME NULL AFTER status;
ALTER TABLE articles ADD INDEX IF NOT EXISTS idx_articles_schedule (status, scheduled_at);
