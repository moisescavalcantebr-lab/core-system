CREATE TABLE IF NOT EXISTS result_sets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NULL,
    title VARCHAR(150) NOT NULL,
    result_type ENUM('match','ranking','evaluation','custom') DEFAULT 'match',
    status ENUM('draft','published','archived') DEFAULT 'draft',
    decided_at DATETIME NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX(match_id),
    INDEX(result_type),
    INDEX(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS result_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    result_set_id INT NOT NULL,
    label VARCHAR(150) NOT NULL,
    value_text VARCHAR(150) NULL,
    value_number DECIMAL(12,2) NULL,
    position INT NULL,
    metadata_json JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX(result_set_id),
    INDEX(position),
    CONSTRAINT fk_result_entries_set
        FOREIGN KEY (result_set_id)
        REFERENCES result_sets(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
