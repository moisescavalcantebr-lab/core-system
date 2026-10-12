CREATE TABLE IF NOT EXISTS content_studio_traffic_approvals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id INT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL,
    snapshot LONGTEXT NOT NULL,
    fingerprint CHAR(64) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    offered_by INT UNSIGNED NOT NULL,
    offered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_by INT UNSIGNED NULL,
    decided_at DATETIME NULL,
    message TEXT NULL,
    UNIQUE KEY uq_traffic_approval_version (request_id, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
