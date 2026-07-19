CREATE TABLE IF NOT EXISTS finance_credit_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    brand VARCHAR(60) NULL,
    last_digits VARCHAR(4) NULL,
    limit_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    closing_day TINYINT UNSIGNED NULL,
    due_day TINYINT UNSIGNED NULL,
    notes TEXT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX(status),
    INDEX(closing_day),
    INDEX(due_day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_credit_card_purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    card_id INT NOT NULL,
    category_id INT NULL,
    title VARCHAR(150) NOT NULL,
    merchant VARCHAR(150) NULL,
    description TEXT NULL,
    purchase_date DATE NOT NULL,
    amount_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    installments_total SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('open','canceled') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX(card_id),
    INDEX(category_id),
    INDEX(purchase_date),
    INDEX(status),
    CONSTRAINT fk_finance_card_purchases_card
        FOREIGN KEY (card_id)
        REFERENCES finance_credit_cards(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_finance_card_purchases_category
        FOREIGN KEY (category_id)
        REFERENCES finance_categories(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_credit_card_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    card_id INT NOT NULL,
    reference_month DATE NOT NULL,
    closing_date DATE NULL,
    due_date DATE NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('open','closed','launched','paid','canceled') DEFAULT 'open',
    finance_entry_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_finance_card_invoice (card_id, reference_month),
    INDEX(card_id),
    INDEX(due_date),
    INDEX(status),
    INDEX(finance_entry_id),
    CONSTRAINT fk_finance_card_invoices_card
        FOREIGN KEY (card_id)
        REFERENCES finance_credit_cards(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_finance_card_invoices_entry
        FOREIGN KEY (finance_entry_id)
        REFERENCES finance_entries(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_credit_card_installments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT NOT NULL,
    invoice_id INT NULL,
    installment_number SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    due_date DATE NOT NULL,
    status ENUM('open','invoiced','paid','canceled') DEFAULT 'open',
    finance_entry_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_finance_card_installment (purchase_id, installment_number),
    INDEX(purchase_id),
    INDEX(invoice_id),
    INDEX(due_date),
    INDEX(status),
    INDEX(finance_entry_id),
    CONSTRAINT fk_finance_card_installments_purchase
        FOREIGN KEY (purchase_id)
        REFERENCES finance_credit_card_purchases(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_finance_card_installments_invoice
        FOREIGN KEY (invoice_id)
        REFERENCES finance_credit_card_invoices(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_finance_card_installments_entry
        FOREIGN KEY (finance_entry_id)
        REFERENCES finance_entries(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
