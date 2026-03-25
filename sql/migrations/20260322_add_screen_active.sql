DELIMITER //

CREATE PROCEDURE migrate_add_screen_active()
BEGIN
    DECLARE v_exists INT DEFAULT 0;

    SELECT COUNT(*)
    INTO v_exists
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'screens'
      AND COLUMN_NAME = 'active';

    IF v_exists = 0 THEN
        ALTER TABLE screens
            ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER playlist_id;
    END IF;

    SELECT COUNT(*)
    INTO v_exists
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'screens'
      AND INDEX_NAME = 'idx_screens_owner_active';

    IF v_exists = 0 THEN
        ALTER TABLE screens
            ADD KEY idx_screens_owner_active (owner_admin_id, active);
    END IF;
END//

DELIMITER ;

CALL migrate_add_screen_active();
DROP PROCEDURE IF EXISTS migrate_add_screen_active;

SELECT 'screen active migration complete' AS status;
