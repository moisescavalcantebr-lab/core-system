CREATE TABLE IF NOT EXISTS competitions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    context ENUM('external','internal') DEFAULT 'external',
    type ENUM('championship','cup','league','tournament','friendly','training','challenge','ranking','other') DEFAULT 'tournament',
    season VARCHAR(80) NULL,
    expected_participants INT NULL,
    expected_matches INT NULL,
    status ENUM('draft','active','finished','canceled') DEFAULT 'active',
    starts_at DATE NULL,
    ends_at DATE NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX(context),
    INDEX(type),
    INDEX(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS competition_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competition_id INT NOT NULL,
    participant_type ENUM('team','player','internal_team') DEFAULT 'team',
    player_id INT NULL,
    name VARCHAR(150) NOT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX(competition_id),
    INDEX(participant_type),
    INDEX(player_id),
    INDEX(status),
    CONSTRAINT fk_competition_participants_competition
        FOREIGN KEY (competition_id)
        REFERENCES competitions(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS competition_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competition_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NULL,
    sort_order INT DEFAULT 0,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX(competition_id),
    INDEX(status),
    CONSTRAINT fk_competition_rules_competition
        FOREIGN KEY (competition_id)
        REFERENCES competitions(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO competitions (name, context, type, season, expected_participants, expected_matches, status, starts_at, ends_at, notes)
SELECT 'Amistoso', 'external', 'friendly', NULL, NULL, NULL, 'active', NULL, NULL, 'Competicao simples para partidas amistosas do plano gratis.'
WHERE NOT EXISTS (
    SELECT 1
    FROM competitions
    WHERE context = 'external'
      AND type = 'friendly'
      AND LOWER(name) = LOWER('Amistoso')
);
