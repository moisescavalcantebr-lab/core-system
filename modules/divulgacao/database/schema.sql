CREATE TABLE IF NOT EXISTS divulgacao_pages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(160) NOT NULL,
    slug VARCHAR(170) NOT NULL,
    template_key VARCHAR(60) NOT NULL DEFAULT 'servico',
    theme VARCHAR(40) NOT NULL DEFAULT 'dark',
    form_language VARCHAR(10) NOT NULL DEFAULT 'pt',
    headline VARCHAR(220) NOT NULL,
    subtitle TEXT NULL,
    body TEXT NULL,
    offer_image VARCHAR(255) NULL,
    offer_image_2 VARCHAR(255) NULL,
    cta_text VARCHAR(80) NOT NULL DEFAULT 'Quero saber mais',
    whatsapp VARCHAR(40) NULL,
    action_type ENUM('capture', 'redirect', 'whatsapp') NOT NULL DEFAULT 'capture',
    destination_url VARCHAR(500) NULL,
    success_message VARCHAR(180) NULL,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_divulgacao_pages_slug (slug),
    KEY idx_divulgacao_pages_status (status),
    KEY idx_divulgacao_pages_template (template_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS divulgacao_leads (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_id INT UNSIGNED NULL,
    name VARCHAR(140) NOT NULL,
    phone VARCHAR(40) NOT NULL,
    email VARCHAR(180) NULL,
    message TEXT NULL,
    status ENUM('novo', 'contatado', 'convertido', 'arquivado') NOT NULL DEFAULT 'novo',
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_divulgacao_leads_page (page_id),
    KEY idx_divulgacao_leads_status (status),
    KEY idx_divulgacao_leads_created (created_at),
    CONSTRAINT fk_divulgacao_leads_page
        FOREIGN KEY (page_id) REFERENCES divulgacao_pages(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
