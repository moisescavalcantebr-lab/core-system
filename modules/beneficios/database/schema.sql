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
