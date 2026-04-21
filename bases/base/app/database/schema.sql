-- =====================================================
-- USERS
-- =====================================================

CREATE TABLE IF NOT EXISTS project_users (
    id INT AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,

    role ENUM('ADMIN','EDITOR','VIEWER','CLIENT') DEFAULT 'CLIENT',
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
