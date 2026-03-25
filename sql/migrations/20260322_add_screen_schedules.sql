DELIMITER //

CREATE PROCEDURE migrate_add_screen_schedules()
BEGIN
    DECLARE v_exists INT DEFAULT 0;

    SELECT COUNT(*)
    INTO v_exists
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'screen_schedules';

    IF v_exists = 0 THEN
        CREATE TABLE screen_schedules (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            screen_id INT UNSIGNED NOT NULL,
            playlist_id INT UNSIGNED NOT NULL,
            label VARCHAR(120) NOT NULL DEFAULT '',
            day_mask TINYINT UNSIGNED NOT NULL DEFAULT 127,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            priority INT UNSIGNED NOT NULL DEFAULT 1,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_screen_schedules_screen_priority (screen_id, active, priority, start_time),
            KEY idx_screen_schedules_playlist (playlist_id),
            CONSTRAINT fk_screen_schedules_screen
                FOREIGN KEY (screen_id) REFERENCES screens(id)
                ON DELETE CASCADE,
            CONSTRAINT fk_screen_schedules_playlist
                FOREIGN KEY (playlist_id) REFERENCES playlists(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    END IF;
END//

DELIMITER ;

CALL migrate_add_screen_schedules();
DROP PROCEDURE IF EXISTS migrate_add_screen_schedules;

SELECT 'screen schedules migration complete' AS status;
