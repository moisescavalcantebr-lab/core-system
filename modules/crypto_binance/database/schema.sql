CREATE TABLE IF NOT EXISTS crypto_binance_reads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mode ENUM('demo','public','account') NOT NULL,
    source_id VARCHAR(64) NOT NULL,
    status ENUM('success','partial','failed') NOT NULL,
    actor_id INT NOT NULL,
    snapshot JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (mode, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crypto_binance_trades (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_id VARCHAR(64) NOT NULL,
    symbol VARCHAR(32) NOT NULL,
    external_id VARCHAR(40) NOT NULL,
    payload JSON NOT NULL,
    last_read_id BIGINT UNSIGNED NOT NULL,
    UNIQUE KEY unique_binance_trade (source_id, symbol, external_id),
    INDEX (last_read_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
