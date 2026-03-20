ALTER TABLE playlist_items
    ADD COLUMN quiz_selection_mode ENUM('fixed', 'random') NOT NULL DEFAULT 'fixed' AFTER item_type;

ALTER TABLE screens
    ADD COLUMN sync_revision INT UNSIGNED NOT NULL DEFAULT 0 AFTER player_version;
