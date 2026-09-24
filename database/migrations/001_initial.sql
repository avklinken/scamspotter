SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS admin_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(191) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(40) NOT NULL DEFAULT 'editor',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scam_families (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(191) NOT NULL UNIQUE,
    summary VARCHAR(500) NOT NULL,
    description TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    sort_order INT NOT NULL DEFAULT 0,
    seo_title VARCHAR(255) NULL,
    meta_description VARCHAR(320) NULL,
    canonical_override VARCHAR(500) NULL,
    published_at DATETIME NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_families_status_sort (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scam_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    family_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(191) NOT NULL UNIQUE,
    summary VARCHAR(500) NOT NULL,
    content LONGTEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    seo_title VARCHAR(255) NULL,
    meta_description VARCHAR(320) NULL,
    canonical_override VARCHAR(500) NULL,
    published_at DATETIME NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_types_family FOREIGN KEY (family_id) REFERENCES scam_families(id) ON DELETE CASCADE,
    KEY idx_types_family_status (family_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scam_variants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(190) NOT NULL,
    slug VARCHAR(191) NOT NULL UNIQUE,
    summary VARCHAR(500) NOT NULL,
    content LONGTEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    seo_title VARCHAR(255) NULL,
    meta_description VARCHAR(320) NULL,
    canonical_override VARCHAR(500) NULL,
    published_at DATETIME NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_variants_type FOREIGN KEY (type_id) REFERENCES scam_types(id) ON DELETE CASCADE,
    KEY idx_variants_type_status (type_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scam_indicators (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    indicator_type VARCHAR(50) NOT NULL,
    value VARCHAR(255) NOT NULL,
    normalized_value VARCHAR(255) NOT NULL,
    explanation VARCHAR(500) NULL,
    weight DECIMAL(6,2) NOT NULL DEFAULT 1.00,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    source_id BIGINT UNSIGNED NULL,
    first_seen DATETIME NULL,
    last_seen DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_indicators_normalized (normalized_value),
    KEY idx_indicators_type_status (indicator_type, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scam_variant_indicators (
    variant_id BIGINT UNSIGNED NOT NULL,
    indicator_id BIGINT UNSIGNED NOT NULL,
    weight_override DECIMAL(6,2) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (variant_id, indicator_id),
    CONSTRAINT fk_variant_indicators_variant FOREIGN KEY (variant_id) REFERENCES scam_variants(id) ON DELETE CASCADE,
    CONSTRAINT fk_variant_indicators_indicator FOREIGN KEY (indicator_id) REFERENCES scam_indicators(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scam_aliases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(30) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    alias VARCHAR(255) NOT NULL,
    normalized_alias VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_alias_value (entity_type, entity_id, normalized_alias),
    KEY idx_alias_lookup (entity_type, normalized_alias),
    KEY idx_alias_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sources (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization VARCHAR(190) NOT NULL,
    title VARCHAR(255) NULL,
    slug VARCHAR(191) NOT NULL UNIQUE,
    homepage VARCHAR(500) NULL,
    feed_url VARCHAR(500) NULL,
    source_type VARCHAR(40) NOT NULL DEFAULT 'website',
    country VARCHAR(3) NOT NULL DEFAULT 'NL',
    trust_status VARCHAR(30) NOT NULL DEFAULT 'pending',
    active TINYINT(1) NOT NULL DEFAULT 1,
    crawl_method VARCHAR(40) NOT NULL DEFAULT 'manual',
    notes TEXT NULL,
    last_success_at DATETIME NULL,
    last_error_at DATETIME NULL,
    last_error TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_sources_active_trust (active, trust_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scam_alerts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    variant_id BIGINT UNSIGNED NULL,
    source_id BIGINT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(191) NOT NULL UNIQUE,
    summary VARCHAR(500) NOT NULL,
    body LONGTEXT NOT NULL,
    event_date DATE NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    published_at DATETIME NULL,
    last_reviewed_at DATETIME NULL,
    seo_title VARCHAR(255) NULL,
    meta_description VARCHAR(320) NULL,
    canonical_override VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_alerts_variant FOREIGN KEY (variant_id) REFERENCES scam_variants(id) ON DELETE SET NULL,
    CONSTRAINT fk_alerts_source FOREIGN KEY (source_id) REFERENCES sources(id) ON DELETE SET NULL,
    KEY idx_alerts_status_date (status, published_at),
    KEY idx_alerts_variant (variant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scam_alert_links (
    alert_id BIGINT UNSIGNED NOT NULL,
    linked_variant_id BIGINT UNSIGNED NOT NULL,
    relation_type VARCHAR(30) NOT NULL DEFAULT 'evergreen',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (alert_id, linked_variant_id),
    CONSTRAINT fk_alert_links_alert FOREIGN KEY (alert_id) REFERENCES scam_alerts(id) ON DELETE CASCADE,
    CONSTRAINT fk_alert_links_variant FOREIGN KEY (linked_variant_id) REFERENCES scam_variants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tags (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(191) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tag_relations (
    tag_id BIGINT UNSIGNED NOT NULL,
    entity_type VARCHAR(30) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (tag_id, entity_type, entity_id),
    CONSTRAINT fk_tag_relations_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
    KEY idx_tag_relations_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS source_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_id BIGINT UNSIGNED NOT NULL,
    external_id VARCHAR(191) NULL,
    url VARCHAR(500) NOT NULL,
    title VARCHAR(255) NOT NULL,
    raw_content LONGTEXT NULL,
    normalized_content LONGTEXT NULL,
    content_hash CHAR(64) NOT NULL,
    published_at DATETIME NULL,
    fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(30) NOT NULL DEFAULT 'new',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_source_items_source FOREIGN KEY (source_id) REFERENCES sources(id) ON DELETE CASCADE,
    UNIQUE KEY uq_source_external (source_id, external_id),
    KEY idx_source_items_hash (content_hash),
    KEY idx_source_items_url (url(191)),
    KEY idx_source_items_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS source_links (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_id BIGINT UNSIGNED NOT NULL,
    source_item_id BIGINT UNSIGNED NULL,
    entity_type VARCHAR(30) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    label VARCHAR(255) NULL,
    published_at DATETIME NULL,
    checked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_source_links_source FOREIGN KEY (source_id) REFERENCES sources(id) ON DELETE CASCADE,
    CONSTRAINT fk_source_links_item FOREIGN KEY (source_item_id) REFERENCES source_items(id) ON DELETE SET NULL,
    KEY idx_source_links_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS checks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    input_type VARCHAR(30) NOT NULL,
    redacted_input VARCHAR(600) NULL,
    input_hash CHAR(64) NOT NULL,
    status VARCHAR(40) NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    KEY idx_checks_ip_created (ip_hash, created_at),
    KEY idx_checks_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS check_matches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    check_id BIGINT UNSIGNED NOT NULL,
    variant_id BIGINT UNSIGNED NOT NULL,
    match_score DECIMAL(8,2) NOT NULL DEFAULT 0,
    match_status VARCHAR(40) NOT NULL,
    matched_indicators LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_check_matches_check FOREIGN KEY (check_id) REFERENCES checks(id) ON DELETE CASCADE,
    CONSTRAINT fk_check_matches_variant FOREIGN KEY (variant_id) REFERENCES scam_variants(id) ON DELETE CASCADE,
    KEY idx_check_matches_check_score (check_id, match_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_analyses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_item_id BIGINT UNSIGNED NULL,
    check_id BIGINT UNSIGNED NULL,
    model VARCHAR(120) NOT NULL,
    prompt_version VARCHAR(40) NOT NULL,
    input_hash CHAR(64) NULL,
    classification VARCHAR(60) NULL,
    output_json LONGTEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'suggestion',
    error_message TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ai_source_item FOREIGN KEY (source_item_id) REFERENCES source_items(id) ON DELETE SET NULL,
    CONSTRAINT fk_ai_check FOREIGN KEY (check_id) REFERENCES checks(id) ON DELETE SET NULL,
    KEY idx_ai_classification (classification),
    KEY idx_ai_source (source_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS review_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_item_id BIGINT UNSIGNED NULL,
    ai_analysis_id BIGINT UNSIGNED NULL,
    item_type VARCHAR(40) NOT NULL DEFAULT 'source_item',
    priority INT NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    suggested_action VARCHAR(255) NULL,
    assigned_to BIGINT UNSIGNED NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_review_source_item FOREIGN KEY (source_item_id) REFERENCES source_items(id) ON DELETE SET NULL,
    CONSTRAINT fk_review_ai FOREIGN KEY (ai_analysis_id) REFERENCES ai_analyses(id) ON DELETE SET NULL,
    CONSTRAINT fk_review_assigned FOREIGN KEY (assigned_to) REFERENCES admin_users(id) ON DELETE SET NULL,
    CONSTRAINT fk_review_reviewed FOREIGN KEY (reviewed_by) REFERENCES admin_users(id) ON DELETE SET NULL,
    KEY idx_review_status_priority (status, priority, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS submissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(30) NOT NULL,
    description TEXT NOT NULL,
    suspicious_text LONGTEXT NULL,
    submitted_url VARCHAR(1000) NULL,
    submitted_email VARCHAR(255) NULL,
    submitted_phone VARCHAR(80) NULL,
    contact_email VARCHAR(255) NULL,
    consent TINYINT(1) NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'new',
    redaction_status VARCHAR(30) NOT NULL DEFAULT 'pending',
    ip_hash CHAR(64) NULL,
    user_agent_hash CHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_submissions_status_date (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS submission_attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL UNIQUE,
    mime_type VARCHAR(120) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_attachments_submission FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS landing_pages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(191) NOT NULL UNIQUE,
    heading VARCHAR(255) NOT NULL,
    intro TEXT NOT NULL,
    input_type VARCHAR(30) NOT NULL,
    campaign_identifier VARCHAR(120) NULL,
    seo_title VARCHAR(255) NULL,
    meta_description VARCHAR(320) NULL,
    indexable TINYINT(1) NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS redirects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    old_path VARCHAR(500) NOT NULL UNIQUE,
    new_path VARCHAR(500) NOT NULL,
    status_code SMALLINT NOT NULL DEFAULT 301,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cron_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_name VARCHAR(120) NOT NULL,
    lock_key VARCHAR(120) NOT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at DATETIME NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'running',
    summary TEXT NULL,
    error_message TEXT NULL,
    stats_json LONGTEXT NULL,
    KEY idx_cron_job_started (job_name, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_user_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    context_json LONGTEXT NULL,
    ip_hash CHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_admin FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE SET NULL,
    KEY idx_audit_entity (entity_type, entity_id),
    KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(191) PRIMARY KEY,
    setting_value LONGTEXT NULL,
    is_secret TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
