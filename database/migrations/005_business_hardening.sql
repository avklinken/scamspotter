SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS business_rate_limits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bucket_key VARCHAR(191) NOT NULL,
    bucket_start DATETIME NOT NULL,
    request_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_business_rate_bucket (bucket_key, bucket_start),
    KEY idx_business_rate_seen (last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS organization_check_feedback (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    check_id BIGINT UNSIGNED NOT NULL,
    feedback VARCHAR(30) NOT NULL,
    comment VARCHAR(1000) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_check_feedback_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_check_feedback_user FOREIGN KEY (user_id) REFERENCES business_users(id) ON DELETE SET NULL,
    CONSTRAINT fk_check_feedback_check FOREIGN KEY (check_id) REFERENCES organization_checks(id) ON DELETE CASCADE,
    UNIQUE KEY uq_check_feedback_user (organization_id, user_id, check_id),
    KEY idx_check_feedback_org (organization_id, feedback, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
