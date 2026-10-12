CREATE TABLE IF NOT EXISTS content_studio_traffic_plans (
    request_id INT UNSIGNED PRIMARY KEY,
    platform VARCHAR(20) NOT NULL,
    objective VARCHAR(120) NOT NULL,
    region VARCHAR(180) NOT NULL,
    audience TEXT NULL,
    placements VARCHAR(255) NOT NULL DEFAULT '',
    ad_title VARCHAR(160) NOT NULL,
    ad_text TEXT NOT NULL,
    creative_url VARCHAR(500) NULL,
    starts_at DATE NOT NULL,
    ends_at DATE NOT NULL,
    external_campaign_id VARCHAR(120) NULL,
    external_campaign_url VARCHAR(500) NULL,
    evidence_url VARCHAR(500) NULL,
    actual_started_at DATETIME NULL,
    actual_ended_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_studio_traffic_reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id INT UNSIGNED NOT NULL,
    report_date DATE NOT NULL,
    impressions INT UNSIGNED NULL,
    link_clicks INT UNSIGNED NULL,
    visits INT UNSIGNED NULL,
    source VARCHAR(180) NOT NULL,
    evidence_url VARCHAR(500) NOT NULL,
    notes TEXT NULL,
    recorded_by INT UNSIGNED NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_traffic_report_day (request_id, report_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
