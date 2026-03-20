-- Adds per-admin ownership to media, playlists, and screens for existing installs.
-- Existing rows are assigned to the oldest admin account (lowest admins.id).
-- Run this once on databases created before the multi-system update.
-- Connect to the target database before running this file.

DROP PROCEDURE IF EXISTS migrate_add_admin_ownership;
DELIMITER //
CREATE PROCEDURE migrate_add_admin_ownership()
BEGIN
    DECLARE v_owner_admin_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_exists INT DEFAULT 0;

    SELECT id
    INTO v_owner_admin_id
    FROM admins
    ORDER BY id ASC
    LIMIT 1;

    IF v_owner_admin_id IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Migration requires at least one admin row in admins.';
    END IF;

    SELECT COUNT(*)
    INTO v_exists
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'media'
      AND COLUMN_NAME = 'owner_admin_id';

    IF v_exists = 0 THEN
        ALTER TABLE media
            ADD COLUMN owner_admin_id INT UNSIGNED NULL AFTER id;
    END IF;

    UPDATE media
    SET owner_admin_id = v_owner_admin_id
    WHERE owner_admin_id IS NULL;

    ALTER TABLE media
        MODIFY COLUMN owner_admin_id INT UNSIGNED NOT NULL;

    SELECT COUNT(*)
    INTO v_exists
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'media'
      AND INDEX_NAME = 'idx_media_owner_active_created';

    IF v_exists = 0 THEN
        ALTER TABLE media
            ADD KEY idx_media_owner_active_created (owner_admin_id, active, created_at);
    END IF;

    SELECT COUNT(*)
    INTO v_exists
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'media'
      AND INDEX_NAME = 'idx_media_owner_type_active';

    IF v_exists = 0 THEN
        ALTER TABLE media
            ADD KEY idx_media_owner_type_active (owner_admin_id, media_type, active);
    END IF;

    SELECT COUNT(*)
    INTO v_exists
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'media'
      AND CONSTRAINT_NAME = 'fk_media_owner_admin';

    IF v_exists = 0 THEN
        ALTER TABLE media
            ADD CONSTRAINT fk_media_owner_admin
                FOREIGN KEY (owner_admin_id) REFERENCES admins(id)
                ON DELETE CASCADE;
    END IF;

    SELECT COUNT(*)
    INTO v_exists
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'playlists'
      AND COLUMN_NAME = 'owner_admin_id';

    IF v_exists = 0 THEN
        ALTER TABLE playlists
            ADD COLUMN owner_admin_id INT UNSIGNED NULL AFTER id;
    END IF;

    UPDATE playlists
    SET owner_admin_id = v_owner_admin_id
    WHERE owner_admin_id IS NULL;

    ALTER TABLE playlists
        MODIFY COLUMN owner_admin_id INT UNSIGNED NOT NULL;

    SELECT COUNT(*)
    INTO v_exists
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'playlists'
      AND INDEX_NAME = 'idx_playlists_owner_active_updated';

    IF v_exists = 0 THEN
        ALTER TABLE playlists
            ADD KEY idx_playlists_owner_active_updated (owner_admin_id, active, updated_at);
    END IF;

    SELECT COUNT(*)
    INTO v_exists
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'playlists'
      AND CONSTRAINT_NAME = 'fk_playlists_owner_admin';

    IF v_exists = 0 THEN
        ALTER TABLE playlists
            ADD CONSTRAINT fk_playlists_owner_admin
                FOREIGN KEY (owner_admin_id) REFERENCES admins(id)
                ON DELETE CASCADE;
    END IF;

    SELECT COUNT(*)
    INTO v_exists
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'screens'
      AND COLUMN_NAME = 'owner_admin_id';

    IF v_exists = 0 THEN
        ALTER TABLE screens
            ADD COLUMN owner_admin_id INT UNSIGNED NULL AFTER id;
    END IF;

    UPDATE screens
    SET owner_admin_id = v_owner_admin_id
    WHERE owner_admin_id IS NULL;

    ALTER TABLE screens
        MODIFY COLUMN owner_admin_id INT UNSIGNED NOT NULL;

    SELECT COUNT(*)
    INTO v_exists
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'screens'
      AND INDEX_NAME = 'idx_screens_owner_playlist';

    IF v_exists = 0 THEN
        ALTER TABLE screens
            ADD KEY idx_screens_owner_playlist (owner_admin_id, playlist_id);
    END IF;

    SELECT COUNT(*)
    INTO v_exists
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'screens'
      AND INDEX_NAME = 'idx_screens_owner_status_last_seen';

    IF v_exists = 0 THEN
        ALTER TABLE screens
            ADD KEY idx_screens_owner_status_last_seen (owner_admin_id, status, last_seen);
    END IF;

    SELECT COUNT(*)
    INTO v_exists
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'screens'
      AND CONSTRAINT_NAME = 'fk_screens_owner_admin';

    IF v_exists = 0 THEN
        ALTER TABLE screens
            ADD CONSTRAINT fk_screens_owner_admin
                FOREIGN KEY (owner_admin_id) REFERENCES admins(id)
                ON DELETE CASCADE;
    END IF;
END//
DELIMITER ;

CALL migrate_add_admin_ownership();
DROP PROCEDURE IF EXISTS migrate_add_admin_ownership;

SELECT 'admin ownership migration complete' AS status;
