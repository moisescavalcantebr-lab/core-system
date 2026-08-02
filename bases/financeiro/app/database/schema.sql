-- =====================================================
-- USERS
-- =====================================================

CREATE TABLE IF NOT EXISTS project_users (
    id INT AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    username VARCHAR(80) NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    must_change_password TINYINT(1) DEFAULT 0,

    role VARCHAR(50) DEFAULT 'CLIENT',
    status ENUM('active','inactive','blocked') DEFAULT 'active',

    avatar VARCHAR(255) NULL,
    bio TEXT NULL,

    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,

    INDEX(role),
    INDEX(status)
) ENGINE=InnoDB;
-- =====================================================
-- PASSWORD RESET
-- =====================================================

CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
-- =====================================================
-- SETTINGS (EVOLUÍDA)
-- =====================================================

CREATE TABLE IF NOT EXISTS project_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value LONGTEXT NULL,
    setting_group VARCHAR(50) DEFAULT 'general',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX(setting_group)
) ENGINE=InnoDB;

INSERT INTO project_settings (setting_key, setting_value, setting_group)
VALUES ('site_name', 'Meu Projeto', 'general')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

INSERT INTO project_settings (setting_key, setting_value, setting_group)
VALUES 
('logo', '', 'branding'),
('favicon', '', 'branding')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- =====================================================
-- LOGS
-- =====================================================

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(255) NOT NULL,
    description TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);


-- =====================================================


-- =====================================================
-- MODULE: financeiro
-- =====================================================
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


-- =====================================================
-- MODULE: categorias
-- =====================================================
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
