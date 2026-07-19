CREATE TABLE IF NOT EXISTS team_profile (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL DEFAULT 'Meu Time',
    short_name VARCHAR(80) NULL,
    team_type ENUM('futsal','society','beach','field','other') DEFAULT 'field',
    starters_count INT DEFAULT 11,
    reserves_count INT DEFAULT 7,
    city VARCHAR(100) NULL,
    venue VARCHAR(150) NULL,
    primary_color VARCHAR(20) NULL,
    secondary_color VARCHAR(20) NULL,
    responsible_name VARCHAR(150) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS team_roster (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    slot_group VARCHAR(40) NULL,
    slot_index INT NULL,
    roster_role ENUM('player','captain','coach') DEFAULT 'player',
    joined_at DATE NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_team_roster_player (player_id),
    INDEX(status),
    INDEX(slot_group, slot_index),
    INDEX(roster_role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO team_profile (id, name)
VALUES (1, 'Meu Time')
ON DUPLICATE KEY UPDATE id = id;
