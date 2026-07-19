CREATE TABLE IF NOT EXISTS finance_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id INT NULL,
    name VARCHAR(120) NOT NULL,
    type ENUM('income','expense','both') DEFAULT 'both',
    form_model ENUM('simple','installment','recurring') DEFAULT 'simple',
    template_key VARCHAR(120) NULL,
    is_system TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_finance_category_name (name),
    INDEX(parent_id),
    INDEX(type),
    INDEX(form_model),
    INDEX(sort_order),
    INDEX(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NULL,
    type ENUM('income','expense') NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    party_type ENUM('user','admin','supplier','customer','member','other') DEFAULT 'other',
    party_module VARCHAR(80) NULL,
    party_id INT NULL,
    party_name VARCHAR(150) NULL,
    due_date DATE NULL,
    paid_at DATE NULL,
    status ENUM('pending','paid','canceled') DEFAULT 'pending',
    source VARCHAR(40) DEFAULT 'manual',
    payment_method VARCHAR(40) NULL,
    receipt_path VARCHAR(255) NULL,
    created_by_user_id INT NULL,
    updated_by_user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX(category_id),
    INDEX(type),
    INDEX(party_type),
    INDEX(party_module),
    INDEX(party_id),
    INDEX(status),
    INDEX(source),
    INDEX(due_date),
    INDEX(created_by_user_id),
    INDEX(updated_by_user_id),
    CONSTRAINT fk_finance_entries_category
        FOREIGN KEY (category_id)
        REFERENCES finance_categories(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_wallet_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    receipt_path VARCHAR(255) NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    reviewed_by_user_id INT NULL,
    reviewed_at DATETIME NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX(user_id),
    INDEX(status),
    INDEX(reviewed_by_user_id),
    CONSTRAINT fk_finance_wallet_requests_user
        FOREIGN KEY (user_id)
        REFERENCES project_users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_entry_tags (
    entry_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (entry_id, tag_id),
    INDEX(tag_id),
    CONSTRAINT fk_finance_entry_tags_entry
        FOREIGN KEY (entry_id)
        REFERENCES finance_entries(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_finance_entry_tags_tag
        FOREIGN KEY (tag_id)
        REFERENCES finance_tags(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO finance_settings (setting_key, setting_value)
VALUES
('finance_mode', 'personal'),
('finance_user_label', 'Usuário'),
('finance_user_label_plural', 'Usuários')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
