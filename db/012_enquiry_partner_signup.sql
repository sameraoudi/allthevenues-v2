-- =============================================================================
-- 012 — enquiries: 'partner_signup' mode + structured partner columns
-- Target database: sameraou_atv2   (MySQL 5.7, apply via phpMyAdmin)
--
-- Preps the enquiries table for Become-a-Venue-Partner submissions:
--   * mode gains 'partner_signup' (a provider signing UP — distinct from the
--     existing 'partner', which means enquiring TO a provider);
--   * three dedicated columns so partner data stores structured, not packed
--     into notes: provider_type, website, venues_managed.
-- Reused as-is: company (org), name (contact), email, phone, city_pref (primary
-- location), notes (message), consent_to_share.
--
-- Folded into db/001_schema.sql for fresh installs. Idempotent on MySQL 5.7
-- (each change guarded via information_schema): re-running is a no-op. Additive
-- only — no data change.
-- =============================================================================

SET NAMES utf8mb4;

-- (a) Extend the mode enum (guard on COLUMN_TYPE).
SET @has := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'enquiries'
      AND COLUMN_NAME  = 'mode'
      AND COLUMN_TYPE LIKE '%partner_signup%'
);
SET @ddl := IF(@has = 0,
    "ALTER TABLE enquiries MODIFY COLUMN mode ENUM('venue','assisted','partner','general','partner_signup') NOT NULL DEFAULT 'general'",
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (b) provider_type — partner_signup provider category.
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enquiries' AND COLUMN_NAME = 'provider_type'
);
SET @ddl := IF(@exists = 0,
    'ALTER TABLE enquiries ADD COLUMN provider_type VARCHAR(50) NULL AFTER partner_id',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (c) website — partner_signup provider site.
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enquiries' AND COLUMN_NAME = 'website'
);
SET @ddl := IF(@exists = 0,
    'ALTER TABLE enquiries ADD COLUMN website VARCHAR(255) NULL AFTER provider_type',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (d) venues_managed — partner_signup number of venues managed.
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enquiries' AND COLUMN_NAME = 'venues_managed'
);
SET @ddl := IF(@exists = 0,
    'ALTER TABLE enquiries ADD COLUMN venues_managed INT UNSIGNED NULL AFTER website',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- End of 012_enquiry_partner_signup.sql
