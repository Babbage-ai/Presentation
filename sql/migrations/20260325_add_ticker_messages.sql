USE cloud_signage_present;

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

ALTER TABLE ticker_messages
    ADD COLUMN IF NOT EXISTS position ENUM('top', 'bottom') NOT NULL DEFAULT 'bottom' AFTER ends_at;

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
