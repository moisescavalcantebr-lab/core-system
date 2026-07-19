CREATE TABLE IF NOT EXISTS scoreboards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NULL,
    title VARCHAR(150) NOT NULL,
    mode ENUM('match','ranking','custom') DEFAULT 'match',
    status ENUM('draft','live','finished','canceled') DEFAULT 'draft',
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX(match_id),
    INDEX(mode),
    INDEX(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scoreboard_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scoreboard_id INT NOT NULL,
    label VARCHAR(150) NOT NULL,
    score DECIMAL(10,2) DEFAULT 0,
    sort_order INT DEFAULT 0,
    metadata_json JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX(scoreboard_id),
    INDEX(sort_order),
    CONSTRAINT fk_scoreboard_items_scoreboard
        FOREIGN KEY (scoreboard_id)
        REFERENCES scoreboards(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
