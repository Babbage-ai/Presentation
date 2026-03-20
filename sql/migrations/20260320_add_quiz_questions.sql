CREATE TABLE IF NOT EXISTS quiz_questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_admin_id INT UNSIGNED NOT NULL,
    question_text TEXT NOT NULL,
    option_a VARCHAR(255) NOT NULL,
    option_b VARCHAR(255) NOT NULL,
    option_c VARCHAR(255) NOT NULL,
    option_d VARCHAR(255) NOT NULL,
    correct_option ENUM('A', 'B', 'C', 'D') NOT NULL,
    countdown_seconds INT UNSIGNED NOT NULL DEFAULT 10,
    reveal_duration INT UNSIGNED NOT NULL DEFAULT 5,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_quiz_questions_owner_active_updated (owner_admin_id, active, updated_at),
    CONSTRAINT fk_quiz_questions_owner_admin
        FOREIGN KEY (owner_admin_id) REFERENCES admins(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE playlist_items
    DROP FOREIGN KEY fk_playlist_items_media;

ALTER TABLE playlist_items
    MODIFY media_id INT UNSIGNED NULL,
    ADD COLUMN item_type ENUM('media', 'quiz') NOT NULL DEFAULT 'media' AFTER playlist_id,
    ADD COLUMN quiz_question_id INT UNSIGNED NULL AFTER media_id,
    ADD KEY idx_playlist_items_quiz (quiz_question_id),
    ADD CONSTRAINT fk_playlist_items_media
        FOREIGN KEY (media_id) REFERENCES media(id)
        ON DELETE RESTRICT,
    ADD CONSTRAINT fk_playlist_items_quiz
        FOREIGN KEY (quiz_question_id) REFERENCES quiz_questions(id)
        ON DELETE RESTRICT;
