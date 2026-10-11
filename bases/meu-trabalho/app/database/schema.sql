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

CREATE TABLE IF NOT EXISTS holerite_attachments (
    receipt_id INT UNSIGNED PRIMARY KEY,
    original_name VARCHAR(200) NOT NULL,
    pdf MEDIUMBLOB NOT NULL,
    CONSTRAINT fk_holerite_attachment_receipt FOREIGN KEY (receipt_id)
        REFERENCES holerite_receipts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- MODULE: beneficios
-- =====================================================
CREATE TABLE IF NOT EXISTS holerite_benefits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT NOT NULL,
    employer VARCHAR(150) NOT NULL,
    registration VARCHAR(60) NOT NULL DEFAULT '',
    reference_month DATE NOT NULL,
    benefit_type ENUM('transport','meal') NOT NULL,
    expected_amount DECIMAL(12,2) NULL,
    credited_amount DECIMAL(12,2) NOT NULL,
    credited_date DATE NULL,
    notes TEXT NULL,
    attachment_name VARCHAR(200) NULL,
    attachment_pdf MEDIUMBLOB NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_benefit_month (owner_user_id, employer, registration, reference_month, benefit_type),
    INDEX idx_benefit_owner_month (owner_user_id, reference_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- MODULE: contas
-- =====================================================
CREATE TABLE IF NOT EXISTS accounts_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT NOT NULL,
    kind ENUM('payable','receivable') NOT NULL,
    title VARCHAR(150) NOT NULL,
    counterparty VARCHAR(150) NULL,
    category VARCHAR(80) NULL,
    amount DECIMAL(12,2) NOT NULL,
    due_date DATE NOT NULL,
    canceled TINYINT(1) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    attachment_name VARCHAR(200) NULL,
    attachment_pdf MEDIUMBLOB NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_account_owner_due (owner_user_id, due_date),
    INDEX idx_account_owner_kind (owner_user_id, kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounts_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    paid_date DATE NOT NULL,
    method VARCHAR(30) NOT NULL,
    notes VARCHAR(500) NULL,
    request_key CHAR(32) NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_account_payment_date (account_id, paid_date),
    CONSTRAINT fk_account_payment_entry FOREIGN KEY (account_id)
        REFERENCES accounts_entries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- MODULE: leituras
-- =====================================================
CREATE TABLE IF NOT EXISTS leituras_reasons (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT NOT NULL,
    number VARCHAR(20) NOT NULL,
    location VARCHAR(120) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_leituras_reason_number (owner_user_id, number),
    UNIQUE KEY uq_leituras_reason_owner (id, owner_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leituras_readings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT NOT NULL,
    reason_id INT UNSIGNED NOT NULL,
    reason_number VARCHAR(20) NOT NULL,
    location VARCHAR(120) NOT NULL,
    work_date DATE NOT NULL,
    total INT UNSIGNED NOT NULL,
    executed INT UNSIGNED NULL,
    sent INT UNSIGNED NULL,
    lis INT UNSIGNED NULL,
    leit INT UNSIGNED NULL,
    notes VARCHAR(1000) NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_leituras_daily (owner_user_id, work_date),
    INDEX idx_leituras_reason_date (reason_id, owner_user_id, work_date),
    CONSTRAINT fk_leituras_reading_reason FOREIGN KEY (reason_id, owner_user_id)
        REFERENCES leituras_reasons(id, owner_user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leituras_workspaces (
    owner_user_id INT PRIMARY KEY,
    active_month CHAR(7) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leituras_periods (
    owner_user_id INT NOT NULL,
    work_month CHAR(7) NOT NULL,
    archived_at DATETIME NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (owner_user_id, work_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
