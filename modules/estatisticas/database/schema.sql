CREATE TABLE IF NOT EXISTS statistic_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    value_mode ENUM('number','boolean','text') DEFAULT 'number',
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS statistic_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    context ENUM('external','internal') DEFAULT 'external',
    competition_id INT NULL,
    match_id INT NULL,
    player_id INT NOT NULL,
    statistic_type_id INT NOT NULL,
    value_number DECIMAL(12,2) NULL,
    value_text VARCHAR(150) NULL,
    recorded_at DATE NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX(context),
    INDEX(competition_id),
    INDEX(match_id),
    INDEX(player_id),
    INDEX(statistic_type_id),
    CONSTRAINT fk_statistic_records_type
        FOREIGN KEY (statistic_type_id)
        REFERENCES statistic_types(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO statistic_types (name, slug, value_mode, status)
VALUES
('Gols', 'gols', 'number', 'active'),
('Assistencias', 'assistencias', 'number', 'active'),
('Cartoes amarelos', 'cartoes_amarelos', 'number', 'active'),
('Cartoes vermelhos', 'cartoes_vermelhos', 'number', 'active'),
('Presenca', 'presenca', 'boolean', 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name);
