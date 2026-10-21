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

CREATE TABLE IF NOT EXISTS finance_team_charges (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    beneficiary_player_id INT NOT NULL,
    beneficiary_name VARCHAR(150) NOT NULL,
    title VARCHAR(150) NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    category_id INT NULL,
    due_date DATE NOT NULL,
    status ENUM('open','canceled') NOT NULL DEFAULT 'open',
    created_by_user_id INT NOT NULL,
    creation_key CHAR(64) NOT NULL,
    creation_fingerprint CHAR(64) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_team_charge_creation(creation_key),
    INDEX(beneficiary_player_id),
    INDEX(status,due_date),
    CONSTRAINT fk_team_charge_category FOREIGN KEY(category_id) REFERENCES finance_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_team_charge_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    charge_id BIGINT UNSIGNED NOT NULL,
    entry_id INT NOT NULL,
    request_key CHAR(64) NOT NULL,
    request_fingerprint CHAR(64) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_team_payment_entry(entry_id),
    UNIQUE KEY unique_team_payment_request(request_key),
    INDEX(charge_id),
    CONSTRAINT fk_team_payment_charge FOREIGN KEY(charge_id) REFERENCES finance_team_charges(id) ON DELETE RESTRICT,
    CONSTRAINT fk_team_payment_entry FOREIGN KEY(entry_id) REFERENCES finance_entries(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_team_charge_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    charge_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(32) NOT NULL,
    actor_user_id INT NOT NULL,
    payment_entry_id INT NULL,
    reason VARCHAR(500) NULL,
    before_json MEDIUMTEXT NULL,
    after_json MEDIUMTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(charge_id),
    INDEX(payment_entry_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
