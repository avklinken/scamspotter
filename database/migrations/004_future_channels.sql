SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS organization_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    rule_type VARCHAR(40) NOT NULL,
    value VARCHAR(255) NOT NULL,
    normalized_value VARCHAR(255) NOT NULL,
    label VARCHAR(190) NULL,
    explanation VARCHAR(500) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_org_rules_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_org_rules_creator FOREIGN KEY (created_by) REFERENCES business_users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_org_rule_value (organization_id, rule_type, normalized_value),
    KEY idx_org_rules_status (organization_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS protect_policies (
    organization_id BIGINT UNSIGNED PRIMARY KEY,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    mode VARCHAR(30) NOT NULL DEFAULT 'manual_review',
    ai_threshold DECIMAL(8,2) NOT NULL DEFAULT 4.00,
    daily_analysis_limit INT UNSIGNED NOT NULL DEFAULT 100,
    retain_raw_until DATETIME NULL,
    updated_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_protect_policy_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_protect_policy_user FOREIGN KEY (updated_by) REFERENCES business_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS analysis_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NULL,
    channel VARCHAR(40) NOT NULL,
    job_type VARCHAR(60) NOT NULL,
    payload_json LONGTEXT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'queued',
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    locked_at DATETIME NULL,
    finished_at DATETIME NULL,
    error_message TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_analysis_jobs_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    KEY idx_analysis_jobs_status_time (status, available_at),
    KEY idx_analysis_jobs_org_status (organization_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS microsoft_graph_subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    microsoft_tenant_id BIGINT UNSIGNED NOT NULL,
    subscription_id VARCHAR(190) NOT NULL UNIQUE,
    resource VARCHAR(500) NOT NULL,
    change_types VARCHAR(120) NOT NULL,
    expiration_at DATETIME NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    last_notification_at DATETIME NULL,
    last_error TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_graph_subscriptions_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_graph_subscriptions_tenant FOREIGN KEY (microsoft_tenant_id) REFERENCES microsoft_tenants(id) ON DELETE CASCADE,
    KEY idx_graph_subscriptions_expiry (status, expiration_at),
    KEY idx_graph_subscriptions_org (organization_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS account_subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL,
    plan_code VARCHAR(40) NOT NULL DEFAULT 'business_trial',
    status VARCHAR(30) NOT NULL DEFAULT 'trialing',
    provider VARCHAR(40) NULL,
    provider_customer_id VARCHAR(190) NULL,
    provider_subscription_id VARCHAR(190) NULL,
    current_period_start DATETIME NULL,
    current_period_end DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_account_subscriptions_account FOREIGN KEY (account_id) REFERENCES commercial_accounts(id) ON DELETE CASCADE,
    UNIQUE KEY uq_account_provider_subscription (provider, provider_subscription_id),
    KEY idx_account_subscriptions_status (account_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS msp_relationships (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    msp_account_id BIGINT UNSIGNED NOT NULL,
    customer_organization_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_msp_customer (msp_account_id, customer_organization_id),
    CONSTRAINT fk_msp_relationship_account FOREIGN KEY (msp_account_id) REFERENCES commercial_accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_msp_relationship_org FOREIGN KEY (customer_organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    KEY idx_msp_relationship_status (msp_account_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS intelligence_feed_deliveries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL,
    delivery_type VARCHAR(40) NOT NULL DEFAULT 'api',
    endpoint VARCHAR(500) NULL,
    secret_hash CHAR(64) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    last_delivered_at DATETIME NULL,
    last_error TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_feed_deliveries_account FOREIGN KEY (account_id) REFERENCES commercial_accounts(id) ON DELETE CASCADE,
    KEY idx_feed_deliveries_account_status (account_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
