CREATE TABLE IF NOT EXISTS participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    name VARCHAR(150) NOT NULL,
    nickname VARCHAR(30) NULL,
    whatsapp VARCHAR(30) NULL,
    birth_date DATE NULL,
    status ENUM('active','inactive','pending') DEFAULT 'active',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_participant_user (user_id),
    INDEX(status),
    INDEX(name),
    INDEX(whatsapp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO project_settings (setting_key, setting_value, setting_group)
VALUES
('participant_context', 'custom', 'participants'),
('participant_label', 'Participante', 'participants'),
('participant_label_plural', 'Participantes', 'participants'),
('participant_public_registration_enabled', '0', 'participants')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
