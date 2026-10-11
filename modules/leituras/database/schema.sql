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
