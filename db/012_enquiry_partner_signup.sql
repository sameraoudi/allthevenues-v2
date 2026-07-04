-- =============================================================================
-- 012 — enquiries.mode += 'partner_signup'
-- Target database: sameraou_atv2   (MySQL 5.7, apply via phpMyAdmin)
--
-- Adds a new enum value so Become-a-Venue-Partner submissions store as leads in
-- the existing admin inbox, distinguishable from event enquiries. NOTE: the
-- existing 'partner' value means enquiring TO a provider — this new value is a
-- provider signing UP, a different thing.
--
-- Folded into db/001_schema.sql (enquiries.mode) for fresh installs. Idempotent
-- on MySQL 5.7 (guarded via information_schema COLUMN_TYPE): re-running is a
-- no-op. Additive enum value — no data change.
-- =============================================================================

SET NAMES utf8mb4;

-- Already extended?
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

-- End of 012_enquiry_partner_signup.sql
