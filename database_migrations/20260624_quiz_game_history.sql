CREATE TABLE IF NOT EXISTS quiz_game_results (
    id BIGINT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    quiz_game_id INT NOT NULL,
    score INT NOT NULL DEFAULT 0,
    total INT NOT NULL DEFAULT 0,
    taken_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_quiz_game_results_user (user_id, taken_at),
    CONSTRAINT fk_qgr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_qgr_game FOREIGN KEY (quiz_game_id) REFERENCES quiz_games(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
