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
