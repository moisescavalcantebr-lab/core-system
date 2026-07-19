CREATE TABLE IF NOT EXISTS live_match_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    player_id INT NOT NULL,
    event_type ENUM('goal') NOT NULL DEFAULT 'goal',
    team_key ENUM('team_1','team_2') NOT NULL DEFAULT 'team_1',
    event_minute INT NULL,
    notes TEXT NULL,
    created_by_user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_live_match_events_match (match_id),
    INDEX idx_live_match_events_player (player_id),
    INDEX idx_live_match_events_type (event_type),
    INDEX idx_live_match_events_team (team_key),
    INDEX idx_live_match_events_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
