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
-- MODULE: meu_holerite
-- =====================================================
CREATE TABLE IF NOT EXISTS holerite_receipts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT NOT NULL,
    employer VARCHAR(150) NOT NULL,
    employee_name VARCHAR(150) NOT NULL,
    registration VARCHAR(60) NULL,
    job_title VARCHAR(150) NULL,
    department VARCHAR(150) NULL,
    admission_date DATE NULL,
    reference_month DATE NOT NULL,
    receipt_type VARCHAR(20) NOT NULL DEFAULT 'monthly',
    sequence_number TINYINT UNSIGNED NOT NULL DEFAULT 1,
    issued_date DATE NULL,
    payment_date DATE NULL,
    received TINYINT(1) NOT NULL DEFAULT 0,
    base_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    inss_base DECIMAL(12,2) NULL,
    irrf_base DECIMAL(12,2) NULL,
    fgts_base DECIMAL(12,2) NULL,
    irrf_recomp_base DECIMAL(12,2) NULL,
    gross_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    deductions_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    net_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_holerite_document (owner_user_id, employer, reference_month, receipt_type, sequence_number),
    INDEX idx_holerite_owner_month (owner_user_id, reference_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS holerite_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    receipt_id INT UNSIGNED NOT NULL,
    event_code VARCHAR(30) NULL,
    description VARCHAR(150) NOT NULL,
    reference_value VARCHAR(40) NULL,
    item_type ENUM('income','deduction') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_holerite_items_receipt FOREIGN KEY (receipt_id)
        REFERENCES holerite_receipts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
