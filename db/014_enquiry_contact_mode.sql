-- =============================================================================
-- 014 — enquiries: add 'contact' to the mode enum
-- Target database: sameraou_atv2   (MySQL 5.7, apply via phpMyAdmin)
--
-- Contact-form submissions currently save as 'general' (the same mode as general
-- event enquiries). This adds a dedicated 'contact' mode so admin can label them
-- and skip the irrelevant "forward to partner" action, without mislabeling real
-- 'general' event enquiries. The code that writes 'contact' is #7b-2.
--
-- Folded into db/001_schema.sql for fresh installs. Idempotent on MySQL 5.7 (no
-- ADD COLUMN IF NOT EXISTS): guarded on COLUMN_TYPE so re-running is a no-op.
-- Additive enum change only — no data change.
-- =============================================================================

SET NAMES utf8mb4;

-- Extend the mode enum (guard on COLUMN_TYPE already containing 'contact').
SET @has := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'enquiries'
      AND COLUMN_NAME  = 'mode'
      AND COLUMN_TYPE LIKE '%''contact''%'
);
SET @ddl := IF(@has = 0,
    "ALTER TABLE enquiries MODIFY COLUMN mode ENUM('venue','assisted','partner','general','partner_signup','contact') NOT NULL DEFAULT 'general'",
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- End of 014_enquiry_contact_mode.sql
