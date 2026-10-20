CREATE TABLE IF NOT EXISTS finance_team_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_id INT NOT NULL,
    action VARCHAR(24) NOT NULL,
    actor_user_id INT NOT NULL,
    reason VARCHAR(500) NULL,
    before_json MEDIUMTEXT NULL,
    after_json MEDIUMTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(entry_id),
    INDEX(actor_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
