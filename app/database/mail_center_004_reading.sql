ALTER TABLE core_mail_preferences ADD COLUMN reading_mode VARCHAR(16) NOT NULL DEFAULT 'panel' AFTER highlight_color;
