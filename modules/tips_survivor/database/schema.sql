CREATE TABLE IF NOT EXISTS tips_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(120) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tips_competitions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    description TEXT NULL,
    season VARCHAR(80) NULL,
    initial_lives TINYINT UNSIGNED NOT NULL DEFAULT 3,
    max_lives TINYINT UNSIGNED NOT NULL DEFAULT 5,
    points_per_extra_life INT UNSIGNED NOT NULL DEFAULT 1000,
    tokens_on_start INT UNSIGNED NOT NULL DEFAULT 30,
    token_consumption_mode ENUM('per_round','per_day') NOT NULL DEFAULT 'per_round',
    token_consumption_amount INT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('draft','open','active','finished','cancelled') NOT NULL DEFAULT 'draft',
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    winner_user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX(status),
    INDEX(starts_at),
    INDEX(winner_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tips_competition_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competition_id INT NOT NULL,
    user_id INT NOT NULL,
    lives TINYINT UNSIGNED NOT NULL DEFAULT 3,
    points INT UNSIGNED NOT NULL DEFAULT 0,
    tokens_generated INT UNSIGNED NOT NULL DEFAULT 0,
    reward_checkpoint INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('active','eliminated','champion') NOT NULL DEFAULT 'active',
    elimination_round INT NULL,
    final_position INT NULL,
    joined_at DATETIME NOT NULL,
    eliminated_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_tips_competition_user (competition_id, user_id),
    INDEX(user_id),
    INDEX(status),
    INDEX(points),
    CONSTRAINT fk_tips_competition_users_competition
        FOREIGN KEY (competition_id) REFERENCES tips_competitions(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tips_matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competition_id INT NOT NULL,
    round_number INT UNSIGNED NOT NULL DEFAULT 1,
    championship_name VARCHAR(160) NULL,
    home_team VARCHAR(120) NOT NULL,
    away_team VARCHAR(120) NOT NULL,
    match_datetime DATETIME NOT NULL,
    status ENUM('scheduled','locked','finished','processed','cancelled') NOT NULL DEFAULT 'scheduled',
    home_score INT NULL,
    away_score INT NULL,
    over_25_result TINYINT(1) NULL,
    both_score_result TINYINT(1) NULL,
    corners_10_result TINYINT(1) NULL,
    cards_result TINYINT(1) NULL,
    processed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX(competition_id),
    INDEX(round_number),
    INDEX(match_datetime),
    INDEX(status),
    CONSTRAINT fk_tips_matches_competition
        FOREIGN KEY (competition_id) REFERENCES tips_competitions(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tips_predictions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competition_id INT NOT NULL,
    match_id INT NOT NULL,
    user_id INT NOT NULL,
    winner_pick ENUM('home','draw','away') NOT NULL,
    home_score_pick INT NULL,
    away_score_pick INT NULL,
    over_25_pick TINYINT(1) NULL,
    both_score_pick TINYINT(1) NULL,
    corners_10_pick TINYINT(1) NULL,
    cards_pick TINYINT(1) NULL,
    points_awarded INT UNSIGNED NOT NULL DEFAULT 0,
    winner_correct TINYINT(1) NULL,
    score_correct TINYINT(1) NULL,
    over_25_correct TINYINT(1) NULL,
    both_score_correct TINYINT(1) NULL,
    corners_10_correct TINYINT(1) NULL,
    cards_correct TINYINT(1) NULL,
    processed TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_tips_prediction_user_match (match_id, user_id),
    INDEX(competition_id),
    INDEX(user_id),
    INDEX(processed),
    CONSTRAINT fk_tips_predictions_competition
        FOREIGN KEY (competition_id) REFERENCES tips_competitions(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_tips_predictions_match
        FOREIGN KEY (match_id) REFERENCES tips_matches(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tips_user_wallets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    tokens INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('free','start') NOT NULL DEFAULT 'free',
    first_start_activated_at DATETIME NULL,
    last_start_activated_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tips_token_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    competition_id INT NULL,
    match_id INT NULL,
    amount INT NOT NULL,
    type ENUM('start_bonus','engagement_bonus','performance_bonus','consumption','extra_points_conversion','admin_adjustment') NOT NULL,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(user_id),
    INDEX(competition_id),
    INDEX(match_id),
    INDEX(type),
    CONSTRAINT fk_tips_token_transactions_competition
        FOREIGN KEY (competition_id) REFERENCES tips_competitions(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_tips_token_transactions_match
        FOREIGN KEY (match_id) REFERENCES tips_matches(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tips_prizes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competition_id INT NOT NULL,
    sponsor_name VARCHAR(160) NULL,
    prize_name VARCHAR(160) NOT NULL,
    prize_description TEXT NULL,
    prize_type ENUM('product','voucher','subscription','badge','other') NOT NULL DEFAULT 'other',
    position INT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('draft','active','delivered','cancelled') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX(competition_id),
    INDEX(status),
    CONSTRAINT fk_tips_prizes_competition
        FOREIGN KEY (competition_id) REFERENCES tips_competitions(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tips_store_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    type ENUM('avatar','frame','banner','theme','badge','highlight','other') NOT NULL DEFAULT 'other',
    price_tokens INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('draft','active','inactive') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX(type),
    INDEX(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tips_user_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    acquired_at DATETIME NOT NULL,
    UNIQUE KEY unique_tips_user_item (user_id, item_id),
    INDEX(item_id),
    CONSTRAINT fk_tips_user_items_item
        FOREIGN KEY (item_id) REFERENCES tips_store_items(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tips_settings (setting_key, setting_value)
VALUES
('initial_lives', '3'),
('max_lives', '5'),
('points_per_extra_life', '1000'),
('tokens_on_start', '30'),
('token_consumption_mode', 'per_round'),
('token_consumption_amount', '1'),
('champion_reward_tokens', '30'),
('points_winner', '10'),
('points_exact_score', '25'),
('points_over_25', '5'),
('points_both_score', '5'),
('points_corners_10', '5'),
('points_cards', '5')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);
