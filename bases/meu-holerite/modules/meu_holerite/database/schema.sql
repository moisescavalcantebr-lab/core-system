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
