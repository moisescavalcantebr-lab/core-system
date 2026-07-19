CREATE TABLE IF NOT EXISTS training_roster (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    team_key ENUM('time_1','time_2') NOT NULL DEFAULT 'time_1',
    status ENUM('field','reserve','inactive') NOT NULL DEFAULT 'reserve',
    slot_group VARCHAR(40) NULL,
    slot_index INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_training_player (player_id),
    INDEX(team_key),
    INDEX(status),
    INDEX(slot_group, slot_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    custom_slots_json JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
