-- =============================================================================
-- 011 — venues contact columns (per-venue contact person)
-- Target database: sameraou_atv2   (MySQL 5.7, apply via phpMyAdmin)
--
-- Three nullable columns holding a dedicated per-venue contact person, separate
-- from the assigned provider: contact_name / contact_email / contact_phone.
-- Internal admin-only details — NEVER rendered on any public page.
--
-- Folded into db/001_schema.sql (venues, AFTER lng) for fresh installs.
-- Idempotent on MySQL 5.7 (no ADD COLUMN IF NOT EXISTS): each column is guarded
-- via information_schema, so re-running is a no-op. New nullable columns — no
-- backfill needed.
-- =============================================================================

SET NAMES utf8mb4;

-- --- contact_name ---
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'venues'
      AND COLUMN_NAME  = 'contact_name'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE venues ADD COLUMN contact_name VARCHAR(255) NULL AFTER lng',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- --- contact_email ---
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'venues'
      AND COLUMN_NAME  = 'contact_email'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE venues ADD COLUMN contact_email VARCHAR(255) NULL AFTER contact_name',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- --- contact_phone ---
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'venues'
      AND COLUMN_NAME  = 'contact_phone'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE venues ADD COLUMN contact_phone VARCHAR(50) NULL AFTER contact_email',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- End of 011_venue_contact.sql
