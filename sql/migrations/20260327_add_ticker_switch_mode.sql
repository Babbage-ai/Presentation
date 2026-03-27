USE cloud_signage_present;

ALTER TABLE ticker_messages
    MODIFY COLUMN position ENUM('top', 'bottom', 'switch') NOT NULL DEFAULT 'bottom';

ALTER TABLE ticker_messages
    ADD COLUMN IF NOT EXISTS flip_interval_seconds INT UNSIGNED NOT NULL DEFAULT 1200 AFTER speed_seconds;
