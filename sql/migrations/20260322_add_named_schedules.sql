DROP PROCEDURE IF EXISTS migrate_add_named_schedules;
DELIMITER $$

CREATE PROCEDURE migrate_add_named_schedules()
BEGIN
    DECLARE has_schedules_table INT DEFAULT 0;
    DECLARE has_schedule_rules_table INT DEFAULT 0;
    DECLARE has_schedule_id_column INT DEFAULT 0;
    DECLARE has_schedule_index INT DEFAULT 0;
    DECLARE has_schedule_fk INT DEFAULT 0;

    SELECT COUNT(*)
    INTO has_schedules_table
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'schedules';

    IF has_schedules_table = 0 THEN
        CREATE TABLE schedules (
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
    END IF;

    SELECT COUNT(*)
    INTO has_schedule_rules_table
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'schedule_rules';

    IF has_schedule_rules_table = 0 THEN
        CREATE TABLE schedule_rules (
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
    END IF;

    SELECT COUNT(*)
    INTO has_schedule_id_column
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'screens'
      AND COLUMN_NAME = 'schedule_id';

    IF has_schedule_id_column = 0 THEN
        ALTER TABLE screens ADD COLUMN schedule_id INT UNSIGNED NULL AFTER playlist_id;
    END IF;

    SELECT COUNT(*)
    INTO has_schedule_index
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'screens'
      AND INDEX_NAME = 'idx_screens_owner_schedule';

    IF has_schedule_index = 0 THEN
        ALTER TABLE screens ADD KEY idx_screens_owner_schedule (owner_admin_id, schedule_id);
    END IF;

    SELECT COUNT(*)
    INTO has_schedule_fk
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'screens'
      AND CONSTRAINT_NAME = 'fk_screens_schedule';

    IF has_schedule_fk = 0 THEN
        ALTER TABLE screens
            ADD CONSTRAINT fk_screens_schedule
            FOREIGN KEY (schedule_id) REFERENCES schedules(id)
            ON DELETE SET NULL;
    END IF;

    IF EXISTS (
        SELECT 1
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'screen_schedules'
    ) THEN
        INSERT INTO schedules (owner_admin_id, name, active, created_at, updated_at)
        SELECT s.owner_admin_id,
               CONCAT(COALESCE(NULLIF(TRIM(s.name), ''), 'Screen'), ' Schedule #', s.id),
               1,
               UTC_TIMESTAMP(),
               UTC_TIMESTAMP()
        FROM screens s
        INNER JOIN screen_schedules ss ON ss.screen_id = s.id
        LEFT JOIN schedules sc
            ON sc.owner_admin_id = s.owner_admin_id
           AND sc.name = CONCAT(COALESCE(NULLIF(TRIM(s.name), ''), 'Screen'), ' Schedule #', s.id)
        WHERE sc.id IS NULL
        GROUP BY s.id, s.owner_admin_id, s.name;

        UPDATE screens s
        INNER JOIN schedules sc
            ON sc.owner_admin_id = s.owner_admin_id
           AND sc.name = CONCAT(COALESCE(NULLIF(TRIM(s.name), ''), 'Screen'), ' Schedule #', s.id)
        SET s.schedule_id = sc.id
        WHERE s.schedule_id IS NULL
          AND EXISTS (SELECT 1 FROM screen_schedules ss WHERE ss.screen_id = s.id);

        INSERT INTO schedule_rules (schedule_id, playlist_id, label, day_mask, start_time, end_time, priority, active, created_at, updated_at)
        SELECT sc.id,
               ss.playlist_id,
               ss.label,
               ss.day_mask,
               ss.start_time,
               ss.end_time,
               ss.priority,
               ss.active,
               ss.created_at,
               ss.updated_at
        FROM screen_schedules ss
        INNER JOIN screens s ON s.id = ss.screen_id
        INNER JOIN schedules sc
            ON sc.owner_admin_id = s.owner_admin_id
           AND sc.name = CONCAT(COALESCE(NULLIF(TRIM(s.name), ''), 'Screen'), ' Schedule #', s.id)
        LEFT JOIN schedule_rules sr
            ON sr.schedule_id = sc.id
           AND sr.playlist_id = ss.playlist_id
           AND sr.label = ss.label
           AND sr.day_mask = ss.day_mask
           AND sr.start_time = ss.start_time
           AND sr.end_time = ss.end_time
           AND sr.priority = ss.priority
           AND sr.active = ss.active
        WHERE sr.id IS NULL;
    END IF;
END$$
DELIMITER ;

CALL migrate_add_named_schedules();
DROP PROCEDURE IF EXISTS migrate_add_named_schedules;
