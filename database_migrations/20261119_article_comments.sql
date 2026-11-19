-- სტატიების კომენტარები (ჩანს მაშინვე; ადმინს შეუძლია დამალვა/წაშლა)
CREATE TABLE IF NOT EXISTS article_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    article_id INT NOT NULL,
    user_id INT NOT NULL,
    body TEXT NOT NULL,
    status ENUM('visible', 'hidden') NOT NULL DEFAULT 'visible',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_article_status_created (article_id, status, created_at),
    KEY idx_user (user_id),
    KEY idx_status_created (status, created_at),
    CONSTRAINT fk_article_comments_article FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    CONSTRAINT fk_article_comments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
