CREATE TABLE IF NOT EXISTS core_mail_connections (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    remote_identity VARCHAR(254) NOT NULL,
    scopes TEXT NOT NULL,
    token_envelope MEDIUMTEXT NULL,
    expires_at DATETIME NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'connected',
    generation INT UNSIGNED NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mail_connection_account (account_id, owner_user_id),
    CONSTRAINT fk_mail_connection_account FOREIGN KEY (account_id, owner_user_id) REFERENCES core_mail_accounts(id, owner_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS core_mail_identities (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(254) NOT NULL,
    display_name VARCHAR(120) NOT NULL,
    can_send TINYINT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY uq_mail_identity_email (account_id, email),
    UNIQUE KEY uq_mail_identity_owner (id, account_id, owner_user_id),
    CONSTRAINT fk_mail_identity_account FOREIGN KEY (account_id, owner_user_id) REFERENCES core_mail_accounts(id, owner_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS core_mail_folders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    remote_id VARCHAR(255) COLLATE utf8mb4_bin NOT NULL,
    name VARCHAR(255) NOT NULL,
    kind VARCHAR(20) NOT NULL DEFAULT 'label',
    UNIQUE KEY uq_mail_folder_remote (account_id, remote_id),
    UNIQUE KEY uq_mail_folder_owner (id, account_id, owner_user_id),
    CONSTRAINT fk_mail_folder_account FOREIGN KEY (account_id, owner_user_id) REFERENCES core_mail_accounts(id, owner_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS core_mail_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    remote_id VARCHAR(255) COLLATE utf8mb4_bin NOT NULL,
    remote_thread_id VARCHAR(255) COLLATE utf8mb4_bin NULL,
    subject VARCHAR(512) NOT NULL DEFAULT '',
    sender VARCHAR(512) NOT NULL DEFAULT '',
    recipients TEXT NOT NULL,
    received_at DATETIME NOT NULL,
    is_unread TINYINT UNSIGNED NOT NULL DEFAULT 0,
    has_attachments TINYINT UNSIGNED NOT NULL DEFAULT 0,
    remote_missing TINYINT UNSIGNED NOT NULL DEFAULT 0,
    synced_at DATETIME NOT NULL,
    UNIQUE KEY uq_mail_message_remote (account_id, remote_id),
    UNIQUE KEY uq_mail_message_owner (id, account_id, owner_user_id),
    KEY idx_mail_message_list (owner_user_id, account_id, received_at, id),
    CONSTRAINT fk_mail_message_account FOREIGN KEY (account_id, owner_user_id) REFERENCES core_mail_accounts(id, owner_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS core_mail_message_folders (
    message_id BIGINT UNSIGNED NOT NULL,
    folder_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    owner_user_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (message_id, folder_id),
    CONSTRAINT fk_mail_mf_message FOREIGN KEY (message_id, account_id, owner_user_id) REFERENCES core_mail_messages(id, account_id, owner_user_id) ON DELETE CASCADE,
    CONSTRAINT fk_mail_mf_folder FOREIGN KEY (folder_id, account_id, owner_user_id) REFERENCES core_mail_folders(id, account_id, owner_user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS core_mail_sync_states (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    scope_key VARCHAR(60) NOT NULL DEFAULT 'mailbox',
    cursor_envelope MEDIUMTEXT NULL,
    lease_token CHAR(32) NULL,
    lease_until DATETIME NULL,
    last_attempt_at DATETIME NULL,
    last_synced_at DATETIME NULL,
    retry_after DATETIME NULL,
    failure_code VARCHAR(30) NULL,
    UNIQUE KEY uq_mail_sync_scope (account_id, scope_key),
    CONSTRAINT fk_mail_sync_account FOREIGN KEY (account_id, owner_user_id) REFERENCES core_mail_accounts(id, owner_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS core_mail_drafts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    identity_id BIGINT UNSIGNED NOT NULL,
    reply_message_id BIGINT UNSIGNED NULL,
    content_envelope MEDIUMTEXT NOT NULL,
    submission_key CHAR(64) NOT NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_mail_draft_owner (id, account_id, owner_user_id),
    CONSTRAINT fk_mail_draft_account FOREIGN KEY (account_id, owner_user_id) REFERENCES core_mail_accounts(id, owner_user_id),
    CONSTRAINT fk_mail_draft_identity FOREIGN KEY (identity_id, account_id, owner_user_id) REFERENCES core_mail_identities(id, account_id, owner_user_id),
    CONSTRAINT fk_mail_draft_reply FOREIGN KEY (reply_message_id, account_id, owner_user_id) REFERENCES core_mail_messages(id, account_id, owner_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS core_mail_attachments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    message_id BIGINT UNSIGNED NULL,
    draft_id BIGINT UNSIGNED NULL,
    part_id VARCHAR(80) NULL,
    remote_attachment_id VARCHAR(512) COLLATE utf8mb4_bin NULL,
    file_token CHAR(32) NULL,
    filename VARCHAR(180) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    byte_size INT UNSIGNED NOT NULL,
    expires_at DATETIME NULL,
    UNIQUE KEY uq_mail_attachment_remote (message_id, part_id),
    CONSTRAINT fk_mail_attachment_message FOREIGN KEY (message_id, account_id, owner_user_id) REFERENCES core_mail_messages(id, account_id, owner_user_id) ON DELETE CASCADE,
    CONSTRAINT fk_mail_attachment_draft FOREIGN KEY (draft_id, account_id, owner_user_id) REFERENCES core_mail_drafts(id, account_id, owner_user_id) ON DELETE CASCADE,
    CONSTRAINT chk_mail_attachment_parent CHECK ((message_id IS NOT NULL AND draft_id IS NULL) OR (message_id IS NULL AND draft_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS core_mail_outbox (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    identity_id BIGINT UNSIGNED NOT NULL,
    draft_id BIGINT UNSIGNED NOT NULL,
    idempotency_key CHAR(64) NOT NULL,
    snapshot_envelope MEDIUMTEXT NULL,
    mime_message_id VARCHAR(255) NOT NULL,
    remote_message_id VARCHAR(255) COLLATE utf8mb4_bin NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'queued',
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    failure_code VARCHAR(30) NULL,
    lease_until DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_mail_outbox_key (owner_user_id, idempotency_key),
    KEY idx_mail_outbox_list (owner_user_id, account_id, status, created_at),
    CONSTRAINT fk_mail_outbox_account FOREIGN KEY (account_id, owner_user_id) REFERENCES core_mail_accounts(id, owner_user_id),
    CONSTRAINT fk_mail_outbox_identity FOREIGN KEY (identity_id, account_id, owner_user_id) REFERENCES core_mail_identities(id, account_id, owner_user_id),
    CONSTRAINT fk_mail_outbox_draft FOREIGN KEY (draft_id, account_id, owner_user_id) REFERENCES core_mail_drafts(id, account_id, owner_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
