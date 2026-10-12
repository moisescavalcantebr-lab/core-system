CREATE TABLE IF NOT EXISTS content_studio_traffic_deliveries (
    request_id INT UNSIGNED PRIMARY KEY,
    snapshot LONGTEXT NOT NULL,
    fingerprint CHAR(64) NOT NULL,
    summary TEXT NOT NULL,
    evidence_url VARCHAR(500) NOT NULL,
    finalized_by INT UNSIGNED NOT NULL,
    finalized_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
