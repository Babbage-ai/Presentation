ALTER TABLE screens
    ADD COLUMN screen_code VARCHAR(6) NULL AFTER name,
    ADD UNIQUE KEY uq_screens_screen_code (screen_code);
