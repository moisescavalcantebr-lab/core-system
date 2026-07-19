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
-- MODULE: crypto_dca_manager
-- =====================================================

CREATE TABLE IF NOT EXISTS crypto_wallets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    description TEXT NULL,
    type VARCHAR(60) DEFAULT 'strategy',
    default_entry_amount DECIMAL(12,2) NOT NULL DEFAULT 50.00,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX(status),
    INDEX(type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crypto_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    description TEXT NULL,
    risk_level ENUM('low','medium','high','watch') DEFAULT 'medium',
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX(status),
    INDEX(risk_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crypto_assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    symbol VARCHAR(24) NOT NULL,
    name VARCHAR(120) NOT NULL,
    pair_usdt VARCHAR(40) NULL,
    pair_usdc VARCHAR(40) NULL,
    wallet_id INT NOT NULL,
    group_id INT NOT NULL,
    strategy_status ENUM('em_observacao','validado','top_5','x2_recuperacao','venda','removido') DEFAULT 'em_observacao',
    base_entry_amount DECIMAL(12,2) NOT NULL DEFAULT 50.00,
    current_entry_amount DECIMAL(12,2) NOT NULL DEFAULT 50.00,
    max_dca TINYINT UNSIGNED NOT NULL DEFAULT 4,
    current_dca_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    reference_asset_symbol VARCHAR(24) DEFAULT 'BTC',
    notes TEXT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_crypto_asset_wallet_symbol (wallet_id, symbol),
    INDEX(group_id),
    INDEX(strategy_status),
    INDEX(status),
    CONSTRAINT fk_crypto_assets_wallet
        FOREIGN KEY (wallet_id) REFERENCES crypto_wallets(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_crypto_assets_group
        FOREIGN KEY (group_id) REFERENCES crypto_groups(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crypto_cycles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    wallet_id INT NOT NULL,
    group_id INT NOT NULL,
    cycle_number INT NOT NULL DEFAULT 1,
    entry_amount DECIMAL(12,2) NOT NULL DEFAULT 50.00,
    total_allocated DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    average_price DECIMAL(20,8) NULL,
    exit_price DECIMAL(20,8) NULL,
    profit_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    profit_percent DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
    dca_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('open','closed','x2_candidate','canceled') DEFAULT 'open',
    opened_at DATE NOT NULL,
    closed_at DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_crypto_asset_cycle (asset_id, cycle_number),
    INDEX(wallet_id),
    INDEX(group_id),
    INDEX(status),
    INDEX(opened_at),
    CONSTRAINT fk_crypto_cycles_asset
        FOREIGN KEY (asset_id) REFERENCES crypto_assets(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_crypto_cycles_wallet
        FOREIGN KEY (wallet_id) REFERENCES crypto_wallets(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_crypto_cycles_group
        FOREIGN KEY (group_id) REFERENCES crypto_groups(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crypto_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cycle_id INT NOT NULL,
    asset_id INT NOT NULL,
    entry_type ENUM('initial','dca','exit','adjustment') DEFAULT 'dca',
    amount_usd DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    price DECIMAL(20,8) NOT NULL DEFAULT 0.00000000,
    quantity DECIMAL(24,10) NOT NULL DEFAULT 0.0000000000,
    dca_level TINYINT UNSIGNED DEFAULT 0,
    executed_at DATE NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX(cycle_id),
    INDEX(asset_id),
    INDEX(entry_type),
    INDEX(executed_at),
    CONSTRAINT fk_crypto_entries_cycle
        FOREIGN KEY (cycle_id) REFERENCES crypto_cycles(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_crypto_entries_asset
        FOREIGN KEY (asset_id) REFERENCES crypto_assets(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crypto_monthly_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    review_month TINYINT UNSIGNED NOT NULL,
    review_year SMALLINT UNSIGNED NOT NULL,
    total_capital DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_profit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_profit_percent DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
    btc_performance_percent DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_crypto_monthly_review (review_year, review_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crypto_asset_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    monthly_review_id INT NOT NULL,
    asset_id INT NOT NULL,
    wallet_id INT NOT NULL,
    group_id INT NOT NULL,
    asset_performance_percent DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
    btc_performance_percent DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
    relative_strength DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
    cycles_closed INT NOT NULL DEFAULT 0,
    recommendation ENUM('manter','top_5','x2','venda','remover') DEFAULT 'manter',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_crypto_asset_review (monthly_review_id, asset_id),
    INDEX(asset_id),
    INDEX(wallet_id),
    INDEX(group_id),
    INDEX(recommendation),
    CONSTRAINT fk_crypto_asset_reviews_monthly
        FOREIGN KEY (monthly_review_id) REFERENCES crypto_monthly_reviews(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_crypto_asset_reviews_asset
        FOREIGN KEY (asset_id) REFERENCES crypto_assets(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_crypto_asset_reviews_wallet
        FOREIGN KEY (wallet_id) REFERENCES crypto_wallets(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_crypto_asset_reviews_group
        FOREIGN KEY (group_id) REFERENCES crypto_groups(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO crypto_wallets (name, slug, description, type, default_entry_amount, status)
VALUES
('Conta Base', 'conta-base', 'Conta principal para tokens validados.', 'base', 50.00, 'active'),
('Conta Observacao', 'conta-observacao', 'Ativos em estudo antes da validacao.', 'watch', 50.00, 'active'),
('Conta Top 5', 'conta-top-5', 'Ativos de maior conviccao estrategica.', 'top_5', 50.00, 'active'),
('Conta X2 / Recuperacao', 'conta-x2-recuperacao', 'Ativos candidatos a recuperacao apos ciclo estendido.', 'x2', 50.00, 'active'),
('Conta Elite', 'conta-elite', 'Conta futura para ativos de alta conviccao.', 'elite', 50.00, 'inactive')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO crypto_groups (name, slug, description, risk_level, status)
VALUES
('Base Mercado', 'base-mercado', 'Referencias e ativos centrais do mercado.', 'low', 'active'),
('Infraestrutura', 'infraestrutura', 'Infra, redes, oraculos, interoperabilidade e ferramentas base.', 'medium', 'active'),
('IA', 'ia', 'Narrativa de inteligencia artificial aplicada a cripto.', 'high', 'active'),
('Novas L1', 'novas-l1', 'Novas camadas 1 e ecossistemas emergentes.', 'high', 'active'),
('Pagamentos / Regulatorios', 'pagamentos-regulatorios', 'Pagamentos, compliance e ativos com narrativa regulatoria.', 'medium', 'active'),
('Observacao', 'observacao', 'Ativos acompanhados sem validacao final.', 'watch', 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name);
