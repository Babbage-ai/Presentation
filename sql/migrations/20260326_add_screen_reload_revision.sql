ALTER TABLE screens
    ADD COLUMN reload_revision INT UNSIGNED NOT NULL DEFAULT 0 AFTER sync_revision;
