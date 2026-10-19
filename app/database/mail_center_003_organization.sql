CREATE TABLE IF NOT EXISTS core_mail_message_marks (
    message_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    owner_user_id INT UNSIGNED NOT NULL,
    is_favorite TINYINT UNSIGNED NOT NULL DEFAULT 0,
    is_flagged TINYINT UNSIGNED NOT NULL DEFAULT 0,
    is_pinned TINYINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (message_id,account_id,owner_user_id),
    KEY idx_mail_marks_owner (owner_user_id,is_favorite,is_flagged,is_pinned),
    CONSTRAINT fk_mail_marks_message FOREIGN KEY (message_id,account_id,owner_user_id) REFERENCES core_mail_messages(id,account_id,owner_user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS core_mail_preferences (
    owner_user_id INT UNSIGNED NOT NULL PRIMARY KEY,
    highlight_color CHAR(7) NOT NULL DEFAULT '#f59e0b',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_mail_preferences_owner FOREIGN KEY (owner_user_id) REFERENCES core_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
