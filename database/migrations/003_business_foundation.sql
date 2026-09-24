SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS commercial_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    slug VARCHAR(191) NOT NULL UNIQUE,
    billing_email VARCHAR(255) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'trial',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_accounts_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS organizations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(190) NOT NULL,
    slug VARCHAR(191) NOT NULL UNIQUE,
    status VARCHAR(30) NOT NULL DEFAULT 'trial',
    timezone VARCHAR(80) NOT NULL DEFAULT 'Europe/Amsterdam',
    retention_days SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_organizations_account FOREIGN KEY (account_id) REFERENCES commercial_accounts(id) ON DELETE CASCADE,
    KEY idx_organizations_account_status (account_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS business_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(191) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    password_hash VARCHAR(255) NULL,
    identity_provider VARCHAR(40) NOT NULL DEFAULT 'local',
    provider_subject VARCHAR(255) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_business_user_provider (identity_provider, provider_subject),
    KEY idx_business_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS organization_memberships (
    organization_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role VARCHAR(30) NOT NULL DEFAULT 'member',
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (organization_id, user_id),
    CONSTRAINT fk_memberships_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_memberships_user FOREIGN KEY (user_id) REFERENCES business_users(id) ON DELETE CASCADE,
    KEY idx_memberships_user_status (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS microsoft_tenants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    tenant_id CHAR(36) NOT NULL UNIQUE,
    display_name VARCHAR(190) NULL,
    consent_status VARCHAR(30) NOT NULL DEFAULT 'pending',
    consented_at DATETIME NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ms_tenants_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    KEY idx_ms_tenants_org_status (organization_id, consent_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS organization_domains (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    domain VARCHAR(255) NOT NULL,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_org_domain (organization_id, domain),
    CONSTRAINT fk_org_domains_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS organization_settings (
    organization_id BIGINT UNSIGNED NOT NULL,
    setting_key VARCHAR(191) NOT NULL,
    setting_value LONGTEXT NULL,
    is_secret TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (organization_id, setting_key),
    CONSTRAINT fk_org_settings_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS organization_analysis_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    channel VARCHAR(40) NOT NULL DEFAULT 'outlook_addin',
    input_type VARCHAR(30) NOT NULL,
    input_hash CHAR(64) NOT NULL,
    status VARCHAR(40) NOT NULL,
    family_id BIGINT UNSIGNED NULL,
    type_id BIGINT UNSIGNED NULL,
    variant_id BIGINT UNSIGNED NULL,
    result_json LONGTEXT NOT NULL,
    model VARCHAR(120) NULL,
    prompt_version VARCHAR(40) NULL,
    input_tokens INT UNSIGNED NULL,
    output_tokens INT UNSIGNED NULL,
    retention_until DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_org_runs_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_org_runs_user FOREIGN KEY (user_id) REFERENCES business_users(id) ON DELETE SET NULL,
    CONSTRAINT fk_org_runs_family FOREIGN KEY (family_id) REFERENCES scam_families(id) ON DELETE SET NULL,
    CONSTRAINT fk_org_runs_type FOREIGN KEY (type_id) REFERENCES scam_types(id) ON DELETE SET NULL,
    CONSTRAINT fk_org_runs_variant FOREIGN KEY (variant_id) REFERENCES scam_variants(id) ON DELETE SET NULL,
    KEY idx_org_runs_org_created (organization_id, created_at),
    KEY idx_org_runs_org_status (organization_id, status),
    KEY idx_org_runs_hash (organization_id, input_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS organization_checks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    analysis_run_id BIGINT UNSIGNED NULL,
    input_type VARCHAR(30) NOT NULL DEFAULT 'email',
    subject VARCHAR(500) NULL,
    sender_email VARCHAR(255) NULL,
    sender_domain VARCHAR(255) NULL,
    redacted_excerpt VARCHAR(1200) NULL,
    message_hash CHAR(64) NOT NULL,
    status VARCHAR(40) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    CONSTRAINT fk_org_checks_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_org_checks_user FOREIGN KEY (user_id) REFERENCES business_users(id) ON DELETE SET NULL,
    CONSTRAINT fk_org_checks_run FOREIGN KEY (analysis_run_id) REFERENCES organization_analysis_runs(id) ON DELETE SET NULL,
    KEY idx_org_checks_org_created (organization_id, created_at),
    KEY idx_org_checks_org_status (organization_id, status),
    KEY idx_org_checks_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS organization_reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    check_id BIGINT UNSIGNED NULL,
    report_type VARCHAR(30) NOT NULL DEFAULT 'suspicious_email',
    description TEXT NULL,
    redacted_excerpt VARCHAR(1200) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'new',
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_org_reports_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_org_reports_user FOREIGN KEY (user_id) REFERENCES business_users(id) ON DELETE SET NULL,
    CONSTRAINT fk_org_reports_check FOREIGN KEY (check_id) REFERENCES organization_checks(id) ON DELETE SET NULL,
    CONSTRAINT fk_org_reports_reviewer FOREIGN KEY (reviewed_by) REFERENCES business_users(id) ON DELETE SET NULL,
    KEY idx_org_reports_org_status (organization_id, status, created_at),
    KEY idx_org_reports_org_created (organization_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS organization_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(80) NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id BIGINT UNSIGNED NULL,
    metadata_json LONGTEXT NULL,
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_org_events_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_org_events_user FOREIGN KEY (user_id) REFERENCES business_users(id) ON DELETE SET NULL,
    KEY idx_org_events_org_type_time (organization_id, event_type, occurred_at),
    KEY idx_org_events_org_time (organization_id, occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usage_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(50) NOT NULL,
    units DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
    model VARCHAR(120) NULL,
    input_tokens INT UNSIGNED NULL,
    output_tokens INT UNSIGNED NULL,
    metadata_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_usage_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_usage_user FOREIGN KEY (user_id) REFERENCES business_users(id) ON DELETE SET NULL,
    KEY idx_usage_org_time (organization_id, created_at),
    KEY idx_usage_org_type_time (organization_id, event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS business_api_keys (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    key_prefix VARCHAR(24) NOT NULL,
    key_hash CHAR(64) NOT NULL UNIQUE,
    last_used_at DATETIME NULL,
    expires_at DATETIME NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_business_keys_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    KEY idx_business_keys_org_status (organization_id, revoked_at, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS organization_invitations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(191) NOT NULL,
    role VARCHAR(30) NOT NULL DEFAULT 'member',
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    accepted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_org_invites_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    KEY idx_org_invites_email (organization_id, email, accepted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
