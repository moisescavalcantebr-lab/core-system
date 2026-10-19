CREATE TABLE IF NOT EXISTS core_mail_schema_versions (
    version VARCHAR(60) NOT NULL PRIMARY KEY,
    checksum CHAR(64) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS core_mail_providers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(60) NOT NULL,
    adapter VARCHAR(30) NOT NULL,
    is_enabled TINYINT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY uq_mail_provider_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS core_mail_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT UNSIGNED NOT NULL,
    name VARCHAR(80) NOT NULL,
    color CHAR(7) NOT NULL DEFAULT '#2563eb',
    display_order INT UNSIGNED NOT NULL DEFAULT 0,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mail_category_name (owner_user_id, name),
    UNIQUE KEY uq_mail_category_owner (id, owner_user_id),
    CONSTRAINT fk_mail_category_user FOREIGN KEY (owner_user_id) REFERENCES core_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS core_mail_accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT UNSIGNED NOT NULL,
    provider_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NULL,
    project_id INT UNSIGNED NULL,
    label VARCHAR(120) NOT NULL,
    email VARCHAR(254) NOT NULL,
    purpose VARCHAR(500) NOT NULL DEFAULT '',
    is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
    connection_status VARCHAR(30) NOT NULL DEFAULT 'not_connected',
    display_order INT UNSIGNED NOT NULL DEFAULT 0,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mail_account_email (owner_user_id, email),
    UNIQUE KEY uq_mail_account_owner (id, owner_user_id),
    KEY idx_mail_account_list (owner_user_id, is_active, display_order),
    KEY idx_mail_account_category (category_id, owner_user_id),
    CONSTRAINT fk_mail_account_user FOREIGN KEY (owner_user_id) REFERENCES core_users(id),
    CONSTRAINT fk_mail_account_provider FOREIGN KEY (provider_id) REFERENCES core_mail_providers(id),
    CONSTRAINT fk_mail_account_category FOREIGN KEY (category_id, owner_user_id) REFERENCES core_mail_categories(id, owner_user_id),
    CONSTRAINT fk_mail_account_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS core_mail_audit_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NULL,
    action VARCHAR(60) NOT NULL,
    result VARCHAR(20) NOT NULL,
    correlation_id CHAR(32) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_mail_audit_owner_action (owner_user_id, action, created_at),
    KEY idx_mail_audit_account (account_id, owner_user_id),
    CONSTRAINT fk_mail_audit_user FOREIGN KEY (owner_user_id) REFERENCES core_users(id),
    CONSTRAINT fk_mail_audit_account FOREIGN KEY (account_id, owner_user_id) REFERENCES core_mail_accounts(id, owner_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO core_mail_providers (code, name, adapter) VALUES
('gmail', 'Gmail', 'gmail'), ('outlook', 'Outlook', 'graph'), ('zoho', 'Zoho Mail', 'zoho')
ON DUPLICATE KEY UPDATE code = VALUES(code);
