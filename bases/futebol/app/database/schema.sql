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
-- MODULE: participantes
-- =====================================================
CREATE TABLE IF NOT EXISTS participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    name VARCHAR(150) NOT NULL,
    nickname VARCHAR(30) NULL,
    whatsapp VARCHAR(30) NULL,
    birth_date DATE NULL,
    status ENUM('active','inactive','pending') DEFAULT 'active',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_participant_user (user_id),
    INDEX(status),
    INDEX(name),
    INDEX(whatsapp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO project_settings (setting_key, setting_value, setting_group)
VALUES
('participant_context', 'jogador', 'participants'),
('participant_label', 'Jogador', 'participants'),
('participant_label_plural', 'Jogadores', 'participants'),
('participant_public_registration_enabled', '0', 'participants')
ON DUPLICATE KEY UPDATE setting_key = setting_key;



-- =====================================================
-- MODULE: financeiro
-- =====================================================
CREATE TABLE IF NOT EXISTS finance_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id INT NULL,
    name VARCHAR(120) NOT NULL,
    type ENUM('income','expense','both') DEFAULT 'both',
    template_key VARCHAR(120) NULL,
    is_system TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_finance_category_name (name),
    INDEX(parent_id),
    INDEX(type),
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

INSERT INTO finance_categories (parent_id, name, type, template_key, is_system, sort_order, status)
VALUES
 (NULL, 'Moradia', 'expense', 'financeiro_pessoal', 1, 10, 'active'),
 (NULL, 'Alimentação', 'expense', 'financeiro_pessoal', 1, 110, 'active'),
 (NULL, 'Transporte', 'expense', 'financeiro_pessoal', 1, 210, 'active'),
 (NULL, 'Saúde', 'expense', 'financeiro_pessoal', 1, 310, 'active'),
 (NULL, 'Educação', 'expense', 'financeiro_pessoal', 1, 410, 'active'),
 (NULL, 'Lazer', 'expense', 'financeiro_pessoal', 1, 510, 'active'),
 (NULL, 'Compras', 'expense', 'financeiro_pessoal', 1, 610, 'active'),
 (NULL, 'Família', 'expense', 'financeiro_pessoal', 1, 710, 'active'),
 (NULL, 'Investimentos', 'income', 'financeiro_pessoal', 1, 810, 'active'),
 (NULL, 'Dívidas', 'expense', 'financeiro_pessoal', 1, 910, 'active'),
 (NULL, 'Trabalho', 'both', 'financeiro_pessoal', 1, 1010, 'active')
ON DUPLICATE KEY UPDATE name = name;

INSERT INTO finance_settings (setting_key, setting_value)
VALUES
('finance_mode', 'participants'),
('finance_user_label', 'Usuário'),
('finance_user_label_plural', 'Usuários')
ON DUPLICATE KEY UPDATE setting_key = setting_key;




-- =====================================================
-- MODULE: jogadores
-- =====================================================
CREATE TABLE IF NOT EXISTS player_positions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NULL,
    name VARCHAR(80) NOT NULL,
    group_key VARCHAR(40) NULL,
    group_label VARCHAR(80) NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_player_position_name (name),
    INDEX(code),
    INDEX(group_key),
    INDEX(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    participant_id INT NULL,
    user_id INT NULL,
    name VARCHAR(120) NOT NULL,
    nickname VARCHAR(14) NULL,
    avatar VARCHAR(255) NULL,
    whatsapp VARCHAR(30) NULL,
    position_id INT NULL,
    secondary_position_id INT NULL,
    position VARCHAR(80) NULL,
    roster_status ENUM('titular','reserva') DEFAULT 'titular',
    shirt_number INT NULL,
    birth_date DATE NULL,
    dominant_foot ENUM('right','left','both') NULL,
    status ENUM('active','inactive') DEFAULT 'inactive',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX(status),
    UNIQUE KEY unique_player_participant (participant_id),
    INDEX(user_id),
    INDEX(whatsapp),
    INDEX(position),
    INDEX(roster_status),
    INDEX(position_id),
    INDEX(secondary_position_id),
    CONSTRAINT fk_players_position
        FOREIGN KEY (position_id)
        REFERENCES player_positions(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO project_settings (setting_key, setting_value, setting_group)
VALUES ('player_public_registration_enabled', '0', 'players')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

INSERT INTO player_positions (code, name, group_key, group_label, sort_order, status)
VALUES
('GO1', 'Goleiro 1', 'goleiro', 'Goleiros', 10, 'active'),
('GO2', 'Goleiro 2', 'goleiro', 'Goleiros', 11, 'active'),
('GO3', 'Goleiro 3', 'goleiro', 'Goleiros', 12, 'active'),
('GO4', 'Goleiro 4', 'goleiro', 'Goleiros', 13, 'active'),
('ZC1', 'Zagueiro 1', 'zagueiro', 'Zagueiros', 20, 'active'),
('ZC2', 'Zagueiro 2', 'zagueiro', 'Zagueiros', 21, 'active'),
('ZC3', 'Zagueiro 3', 'zagueiro', 'Zagueiros', 22, 'active'),
('ZC4', 'Zagueiro 4', 'zagueiro', 'Zagueiros', 23, 'active'),
('LE1', 'Lateral Esquerdo 1', 'lateral', 'Laterais', 30, 'active'),
('LE2', 'Lateral Esquerdo 2', 'lateral', 'Laterais', 31, 'active'),
('LE3', 'Lateral Esquerdo 3', 'lateral', 'Laterais', 32, 'active'),
('LD1', 'Lateral Direito 1', 'lateral', 'Laterais', 33, 'active'),
('LD2', 'Lateral Direito 2', 'lateral', 'Laterais', 34, 'active'),
('LD3', 'Lateral Direito 3', 'lateral', 'Laterais', 35, 'active'),
('VOL1', 'Volante 1', 'meia', 'Meias', 40, 'active'),
('VOL2', 'Volante 2', 'meia', 'Meias', 41, 'active'),
('VOL3', 'Volante 3', 'meia', 'Meias', 42, 'active'),
('MAT1', 'Meia Atacante 1', 'meia', 'Meias', 43, 'active'),
('MAT2', 'Meia Atacante 2', 'meia', 'Meias', 44, 'active'),
('MAT3', 'Meia Atacante 3', 'meia', 'Meias', 45, 'active'),
('PTE1', 'Ponta Esquerda 1', 'ponta', 'Pontas', 50, 'active'),
('PTE2', 'Ponta Esquerda 2', 'ponta', 'Pontas', 51, 'active'),
('PTE3', 'Ponta Esquerda 3', 'ponta', 'Pontas', 52, 'active'),
('PTD1', 'Ponta Direita 1', 'ponta', 'Pontas', 53, 'active'),
('PTD2', 'Ponta Direita 2', 'ponta', 'Pontas', 54, 'active'),
('PTD3', 'Ponta Direita 3', 'ponta', 'Pontas', 55, 'active'),
('CA1', 'Atacante 1', 'atacante', 'Atacantes', 60, 'active'),
('CA2', 'Atacante 2', 'atacante', 'Atacantes', 61, 'active'),
('CA3', 'Atacante 3', 'atacante', 'Atacantes', 62, 'active'),
('CA4', 'Atacante 4', 'atacante', 'Atacantes', 63, 'active')
ON DUPLICATE KEY UPDATE name = name;


-- ADDON: financeiro_futebol
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

CREATE TABLE IF NOT EXISTS finance_team_charge_batches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    allocation_mode ENUM('per_player','split_total') NOT NULL,
    input_amount DECIMAL(12,2) NOT NULL,
    total_amount DECIMAL(14,2) NOT NULL,
    due_date DATE NOT NULL,
    category_id INT NULL,
    plan_json MEDIUMTEXT NOT NULL,
    created_by_user_id INT NOT NULL,
    request_key CHAR(64) NOT NULL,
    request_fingerprint CHAR(64) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_team_batch_request(request_key),
    CONSTRAINT fk_team_batch_category FOREIGN KEY(category_id) REFERENCES finance_categories(id) ON DELETE SET NULL
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

CREATE TABLE IF NOT EXISTS finance_team_charge_batch_items (
    batch_id BIGINT UNSIGNED NOT NULL,
    charge_id BIGINT UNSIGNED NOT NULL,
    player_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    PRIMARY KEY(batch_id,player_id),
    UNIQUE KEY unique_team_batch_charge(charge_id),
    CONSTRAINT fk_team_batch_item_batch FOREIGN KEY(batch_id) REFERENCES finance_team_charge_batches(id) ON DELETE RESTRICT,
    CONSTRAINT fk_team_batch_item_charge FOREIGN KEY(charge_id) REFERENCES finance_team_charges(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_team_mutex(id INT PRIMARY KEY) ENGINE=InnoDB;
INSERT IGNORE INTO finance_team_mutex(id) VALUES(1);
CREATE TABLE IF NOT EXISTS finance_team_accounts(
 id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(120) NOT NULL UNIQUE,created_by_user_id INT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS finance_team_openings(
 account_id INT PRIMARY KEY,amount DECIMAL(12,2) NOT NULL,balance_date DATE NOT NULL,reason VARCHAR(500) NOT NULL,actor_user_id INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS finance_team_account_entries(
 entry_id INT PRIMARY KEY,account_id INT NOT NULL,INDEX(account_id),FOREIGN KEY(entry_id) REFERENCES finance_entries(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS finance_team_transfers(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,from_account_id INT NOT NULL,to_account_id INT NOT NULL,amount DECIMAL(12,2) NOT NULL,movement_date DATE NOT NULL,reason VARCHAR(500) NOT NULL,actor_user_id INT NOT NULL,status ENUM('active','canceled') NOT NULL DEFAULT 'active',request_key CHAR(64) NOT NULL UNIQUE,fingerprint CHAR(64) NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS finance_team_treasury_history(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,action VARCHAR(40) NOT NULL,actor_user_id INT NOT NULL,reason VARCHAR(500) NOT NULL,before_json MEDIUMTEXT NULL,after_json MEDIUMTEXT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS finance_team_credit_movements(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,player_id INT NOT NULL,player_name VARCHAR(150) NOT NULL,kind ENUM('deposit','refund','use') NOT NULL,amount DECIMAL(12,2) NOT NULL,entry_id INT NULL UNIQUE,charge_id BIGINT UNSIGNED NULL,status ENUM('active','canceled') NOT NULL DEFAULT 'active',movement_date DATE NOT NULL,actor_user_id INT NOT NULL,request_key CHAR(64) NOT NULL UNIQUE,fingerprint CHAR(64) NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(player_id,status),INDEX(charge_id,status),FOREIGN KEY(entry_id) REFERENCES finance_entries(id) ON DELETE RESTRICT,FOREIGN KEY(charge_id) REFERENCES finance_team_charges(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS finance_team_credit_history(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,movement_id BIGINT UNSIGNED NOT NULL,action VARCHAR(30) NOT NULL,actor_user_id INT NOT NULL,reason VARCHAR(500) NULL,snapshot_json MEDIUMTEXT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(movement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
