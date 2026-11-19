-- push token-ები მომხმარებელთან კავშირით (პირადი push + წაშლისას გასუფთავება)
ALTER TABLE notification_tokens
    ADD COLUMN user_id INT NULL AFTER token,
    ADD COLUMN updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER subscribed_at,
    ADD KEY idx_notification_tokens_user (user_id),
    ADD CONSTRAINT fk_notification_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
