-- =============================================================================
-- 020 — image rights / provenance (#9a)
-- Target database: sameraou_atv2   (MySQL 5.7, apply via phpMyAdmin)
--
-- Records where each image came from and whether it is cleared for use, on both
-- the per-venue images (venue_images) and the provider cover (partners.cover_*).
-- Nothing is assumed cleared: every EXISTING venue image, and every provider
-- that already has a cover, becomes 'legacy_needs_review' (see #9b for the admin
-- controls + the U-P6b publish-gate tightening that consume this).
--
-- Shared permission_status ENUM (identical in both tables):
--   approved_by_provider            — provider confirmed rights to use it
--   owned_by_atv                    — ATV shot / owns it
--   licensed_stock                  — licensed stock image
--   legacy_needs_review             — migrated/unknown; must be reviewed
--   public_website_needs_permission — scraped from a public site; needs sign-off
--   remove_replace                  — flagged to remove / replace
--
-- Idempotent on MySQL 5.7 (no ADD COLUMN IF NOT EXISTS): every ADD is guarded
-- via information_schema PREPARE/EXECUTE so re-running is a clean no-op. Additive
-- only; the one backfill is itself idempotent (WHERE ... IS NULL).
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- (A) venue_images — per-image provenance + permission
-- -----------------------------------------------------------------------------

-- (A1) permission_status — NOT NULL DEFAULT backfills every existing row.
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venue_images' AND COLUMN_NAME = 'permission_status'
);
SET @ddl := IF(@exists = 0,
    "ALTER TABLE venue_images ADD COLUMN permission_status ENUM('approved_by_provider','owned_by_atv','licensed_stock','legacy_needs_review','public_website_needs_permission','remove_replace') NOT NULL DEFAULT 'legacy_needs_review'",
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (A2) image_source
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venue_images' AND COLUMN_NAME = 'image_source'
);
SET @ddl := IF(@exists = 0,
    'ALTER TABLE venue_images ADD COLUMN image_source VARCHAR(100) NULL',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (A3) source_url
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venue_images' AND COLUMN_NAME = 'source_url'
);
SET @ddl := IF(@exists = 0,
    'ALTER TABLE venue_images ADD COLUMN source_url VARCHAR(255) NULL',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (A4) provider_approved_by
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venue_images' AND COLUMN_NAME = 'provider_approved_by'
);
SET @ddl := IF(@exists = 0,
    'ALTER TABLE venue_images ADD COLUMN provider_approved_by VARCHAR(255) NULL',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (A5) approval_date
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venue_images' AND COLUMN_NAME = 'approval_date'
);
SET @ddl := IF(@exists = 0,
    'ALTER TABLE venue_images ADD COLUMN approval_date DATE NULL',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (A6) usage_notes
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venue_images' AND COLUMN_NAME = 'usage_notes'
);
SET @ddl := IF(@exists = 0,
    'ALTER TABLE venue_images ADD COLUMN usage_notes TEXT NULL',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (A7) expires_at
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venue_images' AND COLUMN_NAME = 'expires_at'
);
SET @ddl := IF(@exists = 0,
    'ALTER TABLE venue_images ADD COLUMN expires_at DATE NULL',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (A8) index for the #9b needs-review filter
SET @exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venue_images' AND INDEX_NAME = 'idx_vimg_permission'
);
SET @ddl := IF(@exists = 0,
    'ALTER TABLE venue_images ADD KEY idx_vimg_permission (permission_status)',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- (B) partners — provider-cover provenance (cover_* ; permission NULLABLE)
-- -----------------------------------------------------------------------------

-- (B1) cover_permission_status — NULL: many providers have no cover.
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'partners' AND COLUMN_NAME = 'cover_permission_status'
);
SET @ddl := IF(@exists = 0,
    "ALTER TABLE partners ADD COLUMN cover_permission_status ENUM('approved_by_provider','owned_by_atv','licensed_stock','legacy_needs_review','public_website_needs_permission','remove_replace') NULL DEFAULT NULL",
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (B2) cover_image_source
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'partners' AND COLUMN_NAME = 'cover_image_source'
);
SET @ddl := IF(@exists = 0,
    'ALTER TABLE partners ADD COLUMN cover_image_source VARCHAR(100) NULL',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (B3) cover_source_url
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'partners' AND COLUMN_NAME = 'cover_source_url'
);
SET @ddl := IF(@exists = 0,
    'ALTER TABLE partners ADD COLUMN cover_source_url VARCHAR(255) NULL',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (B4) cover_provider_approved_by
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'partners' AND COLUMN_NAME = 'cover_provider_approved_by'
);
SET @ddl := IF(@exists = 0,
    'ALTER TABLE partners ADD COLUMN cover_provider_approved_by VARCHAR(255) NULL',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (B5) cover_approval_date
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'partners' AND COLUMN_NAME = 'cover_approval_date'
);
SET @ddl := IF(@exists = 0,
    'ALTER TABLE partners ADD COLUMN cover_approval_date DATE NULL',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (B6) cover_usage_notes
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'partners' AND COLUMN_NAME = 'cover_usage_notes'
);
SET @ddl := IF(@exists = 0,
    'ALTER TABLE partners ADD COLUMN cover_usage_notes TEXT NULL',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (B7) cover_expires_at
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'partners' AND COLUMN_NAME = 'cover_expires_at'
);
SET @ddl := IF(@exists = 0,
    'ALTER TABLE partners ADD COLUMN cover_expires_at DATE NULL',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- (C) Backfill — only providers that actually HAVE a cover become needs-review.
--     Idempotent: the IS NULL guard means a re-run touches nothing.
--     (venue_images needs no backfill — the NOT NULL DEFAULT set every row.)
-- -----------------------------------------------------------------------------
UPDATE partners
   SET cover_permission_status = 'legacy_needs_review'
 WHERE cover_image_path IS NOT NULL
   AND cover_permission_status IS NULL;

-- End of 020_image_provenance.sql
