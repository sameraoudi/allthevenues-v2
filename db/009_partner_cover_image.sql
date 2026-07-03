-- =============================================================================
-- 009 — partners cover-image columns (provider-owned hero image)
-- Target database: sameraou_atv2   (MySQL 5.7, apply via phpMyAdmin)
--
-- Three nullable columns holding a single provider-owned cover/hero image
-- (full + thumbnail + alt), so a provider can have its own landscape image that
-- overrides the venue-derived cover. Mirrors how venue_images stores
-- file_path + thumb_path + alt_text, but as plain columns since a provider has
-- exactly one image. Upload + wiring land in step 2 (no app code this turn).
--
-- Folded into db/001_schema.sql (partners, AFTER logo_path) for fresh installs.
-- Idempotent on MySQL 5.7 (no ADD COLUMN IF NOT EXISTS): each column is guarded
-- via information_schema, so re-running is a no-op. New nullable columns — no
-- backfill needed.
-- =============================================================================

SET NAMES utf8mb4;

-- --- cover_image_path (full image) ---
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'partners'
      AND COLUMN_NAME  = 'cover_image_path'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE partners ADD COLUMN cover_image_path VARCHAR(255) NULL AFTER logo_path',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- --- cover_thumb_path (thumbnail) ---
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'partners'
      AND COLUMN_NAME  = 'cover_thumb_path'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE partners ADD COLUMN cover_thumb_path VARCHAR(255) NULL AFTER cover_image_path',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- --- cover_image_alt (alt text) ---
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'partners'
      AND COLUMN_NAME  = 'cover_image_alt'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE partners ADD COLUMN cover_image_alt VARCHAR(255) NULL AFTER cover_thumb_path',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- End of 009_partner_cover_image.sql
