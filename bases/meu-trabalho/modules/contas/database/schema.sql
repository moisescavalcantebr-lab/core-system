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
