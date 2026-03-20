CREATE DATABASE IF NOT EXISTS cloud_signage CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cloud_signage;

CREATE TABLE IF NOT EXISTS admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS media (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_admin_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    filename VARCHAR(255) NOT NULL UNIQUE,
    mime_type VARCHAR(100) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    duration_seconds INT UNSIGNED NULL,
    media_type ENUM('image', 'video') NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_media_owner_active_created (owner_admin_id, active, created_at),
    KEY idx_media_owner_type_active (owner_admin_id, media_type, active),
    CONSTRAINT fk_media_owner_admin
        FOREIGN KEY (owner_admin_id) REFERENCES admins(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS playlists (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_admin_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_playlists_owner_active_updated (owner_admin_id, active, updated_at),
    CONSTRAINT fk_playlists_owner_admin
        FOREIGN KEY (owner_admin_id) REFERENCES admins(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS playlist_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    playlist_id INT UNSIGNED NOT NULL,
    item_type ENUM('media', 'quiz') NOT NULL DEFAULT 'media',
    quiz_selection_mode ENUM('fixed', 'random') NOT NULL DEFAULT 'fixed',
    media_id INT UNSIGNED NULL,
    quiz_question_id INT UNSIGNED NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 1,
    image_duration INT UNSIGNED NOT NULL DEFAULT 10,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_playlist_items_playlist_sort (playlist_id, sort_order),
    KEY idx_playlist_items_media (media_id),
    KEY idx_playlist_items_quiz (quiz_question_id),
    CONSTRAINT fk_playlist_items_playlist
        FOREIGN KEY (playlist_id) REFERENCES playlists(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_playlist_items_media
        FOREIGN KEY (media_id) REFERENCES media(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_playlist_items_quiz
        FOREIGN KEY (quiz_question_id) REFERENCES quiz_questions(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS screens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_admin_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    screen_token VARCHAR(128) NOT NULL,
    location VARCHAR(255) NOT NULL DEFAULT '',
    playlist_id INT UNSIGNED NULL,
    resolution VARCHAR(50) NULL,
    last_seen DATETIME NULL,
    last_ip VARCHAR(45) NULL,
    status ENUM('online', 'offline') NOT NULL DEFAULT 'offline',
    player_version VARCHAR(50) NULL,
    sync_revision INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_screens_screen_token (screen_token),
    KEY idx_screens_owner_playlist (owner_admin_id, playlist_id),
    KEY idx_screens_owner_status_last_seen (owner_admin_id, status, last_seen),
    CONSTRAINT fk_screens_owner_admin
        FOREIGN KEY (owner_admin_id) REFERENCES admins(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_screens_playlist
        FOREIGN KEY (playlist_id) REFERENCES playlists(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS screen_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    screen_id INT UNSIGNED NOT NULL,
    log_type VARCHAR(50) NOT NULL,
    message VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_screen_logs_screen_created (screen_id, created_at),
    KEY idx_screen_logs_type_created (log_type, created_at),
    CONSTRAINT fk_screen_logs_screen
        FOREIGN KEY (screen_id) REFERENCES screens(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
