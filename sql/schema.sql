CREATE DATABASE IF NOT EXISTS cloud_signage_present CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cloud_signage_present;

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

CREATE TABLE IF NOT EXISTS schedules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_admin_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_schedules_owner_name (owner_admin_id, name),
    KEY idx_schedules_owner_active (owner_admin_id, active),
    CONSTRAINT fk_schedules_owner_admin
        FOREIGN KEY (owner_admin_id) REFERENCES admins(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS screens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_admin_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    screen_code VARCHAR(6) NOT NULL,
    screen_token VARCHAR(128) NOT NULL,
    location VARCHAR(255) NOT NULL DEFAULT '',
    playlist_id INT UNSIGNED NULL,
    schedule_id INT UNSIGNED NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    resolution VARCHAR(50) NULL,
    last_seen DATETIME NULL,
    last_ip VARCHAR(45) NULL,
    status ENUM('online', 'offline') NOT NULL DEFAULT 'offline',
    player_version VARCHAR(50) NULL,
    sync_revision INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_screens_screen_code (screen_code),
    UNIQUE KEY uq_screens_screen_token (screen_token),
    KEY idx_screens_owner_active (owner_admin_id, active),
    KEY idx_screens_owner_playlist (owner_admin_id, playlist_id),
    KEY idx_screens_owner_schedule (owner_admin_id, schedule_id),
    KEY idx_screens_owner_status_last_seen (owner_admin_id, status, last_seen),
    CONSTRAINT fk_screens_owner_admin
        FOREIGN KEY (owner_admin_id) REFERENCES admins(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_screens_playlist
        FOREIGN KEY (playlist_id) REFERENCES playlists(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_screens_schedule
        FOREIGN KEY (schedule_id) REFERENCES schedules(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schedule_rules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT UNSIGNED NOT NULL,
    playlist_id INT UNSIGNED NOT NULL,
    label VARCHAR(120) NOT NULL DEFAULT '',
    day_mask TINYINT UNSIGNED NOT NULL DEFAULT 127,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    priority INT UNSIGNED NOT NULL DEFAULT 1,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_schedule_rules_schedule_priority (schedule_id, active, priority, start_time),
    KEY idx_schedule_rules_playlist (playlist_id),
    CONSTRAINT fk_schedule_rules_schedule
        FOREIGN KEY (schedule_id) REFERENCES schedules(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_schedule_rules_playlist
        FOREIGN KEY (playlist_id) REFERENCES playlists(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticker_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_admin_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    message_text TEXT NOT NULL,
    day_mask TINYINT UNSIGNED NOT NULL DEFAULT 127,
    start_time TIME NOT NULL DEFAULT '00:00:00',
    end_time TIME NOT NULL DEFAULT '23:59:59',
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    position ENUM('top', 'bottom') NOT NULL DEFAULT 'bottom',
    speed_seconds INT UNSIGNED NOT NULL DEFAULT 28,
    priority INT UNSIGNED NOT NULL DEFAULT 1,
    active TINYINT(1) NOT NULL DEFAULT 1,
    applies_to_all_screens TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ticker_messages_owner_name (owner_admin_id, name),
    KEY idx_ticker_messages_owner_active_priority (owner_admin_id, active, priority),
    CONSTRAINT fk_ticker_messages_owner_admin
        FOREIGN KEY (owner_admin_id) REFERENCES admins(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticker_message_screens (
    ticker_message_id INT UNSIGNED NOT NULL,
    screen_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ticker_message_id, screen_id),
    KEY idx_ticker_message_screens_screen (screen_id),
    CONSTRAINT fk_ticker_message_screens_message
        FOREIGN KEY (ticker_message_id) REFERENCES ticker_messages(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_ticker_message_screens_screen
        FOREIGN KEY (screen_id) REFERENCES screens(id)
        ON DELETE CASCADE
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
