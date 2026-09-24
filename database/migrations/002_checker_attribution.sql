ALTER TABLE checks
    ADD COLUMN campaign_identifier VARCHAR(120) NULL AFTER input_type,
    ADD COLUMN attribution_json TEXT NULL AFTER ip_hash,
    ADD KEY idx_checks_campaign (campaign_identifier);
