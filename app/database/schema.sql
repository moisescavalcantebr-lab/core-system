SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =========================================
-- CORE SETTINGS 
-- =========================================

CREATE TABLE IF NOT EXISTS core_settings (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 setting_key VARCHAR(100) NOT NULL,
 setting_value TEXT,
 updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
 ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uniq_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =========================================
-- CORE USERS 
-- =========================================

CREATE TABLE IF NOT EXISTS core_users (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL,
 email VARCHAR(150) NOT NULL,
 password VARCHAR(255) NOT NULL,
 avatar VARCHAR(255) NULL,
 role ENUM('SUPER_ADMIN','ADMIN','USER') DEFAULT 'USER',
 status TINYINT DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uniq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================
-- BASES 
-- =========================================

CREATE TABLE IF NOT EXISTS bases (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 cloned_from_id INT UNSIGNED NULL,
 name VARCHAR(150) NOT NULL,
 slug VARCHAR(150) NOT NULL,
 description TEXT,
 allows_users TINYINT DEFAULT 1,
 max_admins INT DEFAULT 1,
 status TINYINT DEFAULT 1,
 is_protected TINYINT DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uniq_slug (slug),
 CONSTRAINT fk_bases_cloned
 FOREIGN KEY (cloned_from_id)
 REFERENCES bases(id)
 ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- BASE DEFAULT AUTOMÁTICA
INSERT INTO bases (
    name,
    slug,
    description,
    allows_users,
    max_admins,
    status,
    is_protected
)
VALUES (
    'Base',
    'base',
    'Base inicial do sistema (não deletar)',
    1,
    1,
    1,
    1
)
ON DUPLICATE KEY UPDATE slug = slug;

