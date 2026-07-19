CREATE TABLE IF NOT EXISTS lineup_sheets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NULL,
    title VARCHAR(150) NOT NULL,
    group_name VARCHAR(100) NULL,
    status ENUM('draft','published','archived') DEFAULT 'draft',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX(match_id),
    INDEX(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lineup_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lineup_id INT NOT NULL,
    source_module VARCHAR(80) NULL,
    source_id INT NULL,
    display_name VARCHAR(150) NOT NULL,
    role ENUM('starter','reserve','coach','staff','participant','support') DEFAULT 'participant',
    position_label VARCHAR(100) NULL,
    sort_order INT DEFAULT 0,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX(lineup_id),
    INDEX(source_module),
    INDEX(source_id),
    INDEX(role),
    CONSTRAINT fk_lineup_members_sheet
        FOREIGN KEY (lineup_id)
        REFERENCES lineup_sheets(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
