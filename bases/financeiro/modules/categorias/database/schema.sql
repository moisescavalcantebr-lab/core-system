CREATE TABLE IF NOT EXISTS finance_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_entry_tags (
    entry_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (entry_id, tag_id),
    INDEX(tag_id),
    CONSTRAINT fk_finance_entry_tags_entry
        FOREIGN KEY (entry_id)
        REFERENCES finance_entries(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_finance_entry_tags_tag
        FOREIGN KEY (tag_id)
        REFERENCES finance_tags(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
