-- =============================================================================
-- 007 — venues.website (restore legacy venue website field)
-- Target database: sameraou_atv2   (MySQL 5.7, apply via phpMyAdmin)
--
-- The legacy `venues.website` column was not carried over by the U1b catalogue
-- migration. This adds the column; db/backfill_venue_website.php then populates
-- it from the legacy DB (validated/normalized URLs only).
--
-- Folded into db/001_schema.sql (venues.website AFTER video_url) for fresh
-- installs. Idempotent: guarded so re-running is a no-op if the column exists.
-- =============================================================================

SET NAMES utf8mb4;

-- Add venues.website only if it isn't already present (re-runnable on MySQL 5.7,
-- which has no ADD COLUMN IF NOT EXISTS).
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'venues'
      AND COLUMN_NAME  = 'website'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE venues ADD COLUMN website VARCHAR(255) NULL AFTER video_url',
    'DO 0');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
