CREATE TABLE IF NOT EXISTS classification_tables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competition_id INT NULL,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    active_fields JSON NOT NULL,
    sort_field VARCHAR(80) DEFAULT 'position',
    sort_direction ENUM('asc','desc') DEFAULT 'asc',
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX(competition_id),
    INDEX(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS classification_rows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_id INT NOT NULL,
    name VARCHAR(120) NOT NULL,
    data_json JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX(table_id),
    CONSTRAINT fk_classification_rows_table
        FOREIGN KEY (table_id)
        REFERENCES classification_tables(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
