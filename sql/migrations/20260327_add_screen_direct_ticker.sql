USE cloud_signage_present;

ALTER TABLE screens
    ADD COLUMN IF NOT EXISTS ticker_message_id INT UNSIGNED NULL AFTER schedule_id;

ALTER TABLE screens
    ADD KEY idx_screens_owner_ticker (owner_admin_id, ticker_message_id);

ALTER TABLE screens
    ADD CONSTRAINT fk_screens_ticker_message
        FOREIGN KEY (ticker_message_id) REFERENCES ticker_messages(id)
        ON DELETE SET NULL;
