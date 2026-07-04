-- =============================================================================
-- 015 — enquiries: is_historical + legacy_id (prep for the legacy backfill)
-- Target database: sameraou_atv2   (MySQL 5.7, apply via phpMyAdmin)
--
-- Prepares the enquiries table for migrating the legacy `inquiry` rows (U3c-2):
--   * is_historical — separates migrated legacy enquiries from new leads in
--     reporting (new leads stay 0);
--   * legacy_id     — the source row's id, for idempotency + traceability. A
--     UNIQUE key blocks double-importing the same legacy row; NULLs are allowed
--     in a MySQL unique index, so new enquiries (legacy_id NULL) are unaffected.
--
-- Folded into db/001_schema.sql for fresh installs. Idempotent on MySQL 5.7 (no
-- ADD COLUMN IF NOT EXISTS): each change guarded via information_schema so
-- re-running is a no-op. Additive only — no data change.
-- =============================================================================

SET NAMES utf8mb4;

-- (a) is_historical — migrated vs new lead.
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enquiries' AND COLUMN_NAME = 'is_historical'
);
SET @ddl := IF(@exists = 0,
    'ALTER TABLE enquiries ADD COLUMN is_historical TINYINT(1) NOT NULL DEFAULT 0 AFTER status',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (b) legacy_id — source row id (idempotency/traceability).
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enquiries' AND COLUMN_NAME = 'legacy_id'
);
SET @ddl := IF(@exists = 0,
    'ALTER TABLE enquiries ADD COLUMN legacy_id INT UNSIGNED NULL AFTER is_historical',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (c) unique key on legacy_id (blocks double-import; NULLs allowed).
SET @exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enquiries' AND INDEX_NAME = 'uq_enquiries_legacy'
);
SET @ddl := IF(@exists = 0,
    'ALTER TABLE enquiries ADD UNIQUE KEY uq_enquiries_legacy (legacy_id)',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- End of 015_enquiry_historical.sql
