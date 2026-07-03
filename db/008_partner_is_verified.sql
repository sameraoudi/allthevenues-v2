-- =============================================================================
-- 008 — partners.is_verified (real Verified-provider column)
-- Target database: sameraou_atv2   (MySQL 5.7, apply via phpMyAdmin)
--
-- Until now partner_is_verified() aliased is_featured (no real column). This
-- adds the column so Verified (editorial trust) is curated separately from
-- Featured (curation/paid). Mirrors venues.is_verified.
--
-- Folded into db/001_schema.sql (partners, AFTER is_featured) for fresh installs.
-- Idempotent on MySQL 5.7 (no ADD COLUMN IF NOT EXISTS): guarded so re-running
-- is a no-op and NEVER overwrites later admin-curated values.
-- =============================================================================

SET NAMES utf8mb4;

-- Was the column already present before this run?
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'partners'
      AND COLUMN_NAME  = 'is_verified'
);

-- Add the column only if missing.
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE partners ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER is_featured',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill ONLY on first creation, so re-runs can't clobber admin edits.
SET @bf := IF(@col_exists = 0,
    'UPDATE partners SET is_verified = is_featured',
    'DO 0');
PREPARE stmt FROM @bf; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- End of 008_partner_is_verified.sql
