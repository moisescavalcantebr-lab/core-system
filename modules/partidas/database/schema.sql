CREATE TABLE IF NOT EXISTS match_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    season VARCHAR(80) NULL,
    category VARCHAR(80) NULL,
    status ENUM('draft','active','finished','canceled') DEFAULT 'active',
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX(status),
    INDEX(category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competition_id INT NULL,
    event_id INT NULL,
    title VARCHAR(150) NULL,
    participant_a VARCHAR(150) NOT NULL,
    participant_b VARCHAR(150) NULL,
    score_a INT NULL,
    score_b INT NULL,
    yellow_cards_a INT NOT NULL DEFAULT 0,
    yellow_cards_b INT NOT NULL DEFAULT 0,
    red_cards_a INT NOT NULL DEFAULT 0,
    red_cards_b INT NOT NULL DEFAULT 0,
    match_date DATETIME NULL,
    venue VARCHAR(150) NULL,
    round_name VARCHAR(80) NULL,
    status ENUM('scheduled','live','finished','canceled') DEFAULT 'scheduled',
    lineup_mode enum('team_roster','arrival_order','automatic') NOT NULL DEFAULT 'team_roster',
    field_type_snapshot VARCHAR(40) NULL,
    field_slots_snapshot_json TEXT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX(event_id),
    INDEX(competition_id),
    INDEX(status),
    INDEX(match_date),
    CONSTRAINT fk_matches_event
        FOREIGN KEY (event_id)
        REFERENCES match_events(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS match_confirmations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    player_id INT NOT NULL,
    status ENUM('confirmed','declined') NOT NULL DEFAULT 'confirmed',
    notes TEXT NULL,
    confirmed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_match_player_confirmation (match_id, player_id),
    INDEX(match_id),
    INDEX(player_id),
    INDEX(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS match_confirmation_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    player_id INT NOT NULL,
    status ENUM('confirmed','declined') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(match_id),
    INDEX(player_id),
    INDEX(status),
    INDEX(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS match_lineup (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    player_id INT NOT NULL,
    status ENUM('starter','reserve') NOT NULL DEFAULT 'reserve',
    lineup_team ENUM('team_1','team_2') NULL,
    slot_group VARCHAR(40) NULL,
    slot_index INT NULL,
    override_position_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_match_player_lineup (match_id, player_id),
    INDEX(match_id),
    INDEX(player_id),
        INDEX(status),
    INDEX(lineup_team),
    INDEX(slot_group),
    INDEX(override_position_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS match_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    player_id INT NOT NULL,
    status ENUM('present','excused_absence','no_response','confirmed_absent','justified_absent') NOT NULL DEFAULT 'no_response',
    points DECIMAL(4,1) NOT NULL DEFAULT 0.0,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_match_player_attendance (match_id, player_id),
    INDEX(match_id),
    INDEX(player_id),
    INDEX(status),
    INDEX(points)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
