CREATE TABLE IF NOT EXISTS player_positions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NULL,
    name VARCHAR(80) NOT NULL,
    group_key VARCHAR(40) NULL,
    group_label VARCHAR(80) NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_player_position_name (name),
    INDEX(code),
    INDEX(group_key),
    INDEX(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    participant_id INT NULL,
    user_id INT NULL,
    name VARCHAR(120) NOT NULL,
    nickname VARCHAR(14) NULL,
    avatar VARCHAR(255) NULL,
    whatsapp VARCHAR(30) NULL,
    position_id INT NULL,
    secondary_position_id INT NULL,
    position VARCHAR(80) NULL,
    roster_status ENUM('titular','reserva') DEFAULT 'titular',
    shirt_number INT NULL,
    birth_date DATE NULL,
    dominant_foot ENUM('right','left','both') NULL,
    status ENUM('active','inactive') DEFAULT 'inactive',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX(status),
    UNIQUE KEY unique_player_participant (participant_id),
    INDEX(user_id),
    INDEX(whatsapp),
    INDEX(position),
    INDEX(roster_status),
    INDEX(position_id),
    INDEX(secondary_position_id),
    CONSTRAINT fk_players_position
        FOREIGN KEY (position_id)
        REFERENCES player_positions(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO project_settings (setting_key, setting_value, setting_group)
VALUES ('player_public_registration_enabled', '0', 'players')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

INSERT INTO player_positions (code, name, group_key, group_label, sort_order, status)
VALUES
('GO1', 'Goleiro 1', 'goleiro', 'Goleiros', 10, 'active'),
('GO2', 'Goleiro 2', 'goleiro', 'Goleiros', 11, 'active'),
('GO3', 'Goleiro 3', 'goleiro', 'Goleiros', 12, 'active'),
('GO4', 'Goleiro 4', 'goleiro', 'Goleiros', 13, 'active'),
('ZC1', 'Zagueiro 1', 'zagueiro', 'Zagueiros', 20, 'active'),
('ZC2', 'Zagueiro 2', 'zagueiro', 'Zagueiros', 21, 'active'),
('ZC3', 'Zagueiro 3', 'zagueiro', 'Zagueiros', 22, 'active'),
('ZC4', 'Zagueiro 4', 'zagueiro', 'Zagueiros', 23, 'active'),
('LE1', 'Lateral Esquerdo 1', 'lateral', 'Laterais', 30, 'active'),
('LE2', 'Lateral Esquerdo 2', 'lateral', 'Laterais', 31, 'active'),
('LE3', 'Lateral Esquerdo 3', 'lateral', 'Laterais', 32, 'active'),
('LD1', 'Lateral Direito 1', 'lateral', 'Laterais', 33, 'active'),
('LD2', 'Lateral Direito 2', 'lateral', 'Laterais', 34, 'active'),
('LD3', 'Lateral Direito 3', 'lateral', 'Laterais', 35, 'active'),
('VOL1', 'Volante 1', 'meia', 'Meias', 40, 'active'),
('VOL2', 'Volante 2', 'meia', 'Meias', 41, 'active'),
('VOL3', 'Volante 3', 'meia', 'Meias', 42, 'active'),
('MAT1', 'Meia Atacante 1', 'meia', 'Meias', 43, 'active'),
('MAT2', 'Meia Atacante 2', 'meia', 'Meias', 44, 'active'),
('MAT3', 'Meia Atacante 3', 'meia', 'Meias', 45, 'active'),
('PTE1', 'Ponta Esquerda 1', 'ponta', 'Pontas', 50, 'active'),
('PTE2', 'Ponta Esquerda 2', 'ponta', 'Pontas', 51, 'active'),
('PTE3', 'Ponta Esquerda 3', 'ponta', 'Pontas', 52, 'active'),
('PTD1', 'Ponta Direita 1', 'ponta', 'Pontas', 53, 'active'),
('PTD2', 'Ponta Direita 2', 'ponta', 'Pontas', 54, 'active'),
('PTD3', 'Ponta Direita 3', 'ponta', 'Pontas', 55, 'active'),
('CA1', 'Atacante 1', 'atacante', 'Atacantes', 60, 'active'),
('CA2', 'Atacante 2', 'atacante', 'Atacantes', 61, 'active'),
('CA3', 'Atacante 3', 'atacante', 'Atacantes', 62, 'active'),
('CA4', 'Atacante 4', 'atacante', 'Atacantes', 63, 'active')
ON DUPLICATE KEY UPDATE name = name;
