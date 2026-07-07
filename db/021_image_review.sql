-- =============================================================================
-- 021 — venue_images review workflow (#3 U-P7a: provider image uploads)
-- Target database: sameraou_atv2   (MySQL 5.7, apply via phpMyAdmin)
--
-- Adds a provider-submission review workflow on top of the existing display
-- `status` ENUM(active/hidden) and the #9 `permission_status` (admin-owned) — it
-- touches NEITHER of those. Provider uploads land review_status='pending_review'
-- + status='hidden' (invisible publicly, cannot satisfy any publish gate) until
-- an admin approves them to live in U-P7b.
--
-- IMPORTANT: review_status DEFAULT 'approved' backfills every EXISTING row — they
-- are already live/approved. The provider insert path sets 'pending_review'
-- explicitly. rights_confirmed_* record the PROVIDER's rights confirmation, which
-- is distinct from ATV editorial approval (#9 permission_status).
--
-- Idempotent on MySQL 5.7 (no ADD COLUMN IF NOT EXISTS): each ADD is guarded via
-- information_schema PREPARE/EXECUTE, so re-running is a clean no-op. Additive
-- only; existing rows change only by inheriting the review_status default.
-- =============================================================================

SET NAMES utf8mb4;

-- (a) review_status — DEFAULT 'approved' backfills existing rows.
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venue_images' AND COLUMN_NAME = 'review_status');
SET @ddl := IF(@exists = 0,
    "ALTER TABLE venue_images ADD COLUMN review_status ENUM('pending_review','approved','rejected','withdrawn','archived') NOT NULL DEFAULT 'approved'",
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (b) rights_confirmed
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venue_images' AND COLUMN_NAME = 'rights_confirmed');
SET @ddl := IF(@exists = 0,
    'ALTER TABLE venue_images ADD COLUMN rights_confirmed TINYINT(1) NOT NULL DEFAULT 0',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (c) rights_confirmed_by
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venue_images' AND COLUMN_NAME = 'rights_confirmed_by');
SET @ddl := IF(@exists = 0,
    'ALTER TABLE venue_images ADD COLUMN rights_confirmed_by VARCHAR(255) NULL',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (d) rights_confirmed_at
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venue_images' AND COLUMN_NAME = 'rights_confirmed_at');
SET @ddl := IF(@exists = 0,
    'ALTER TABLE venue_images ADD COLUMN rights_confirmed_at DATETIME NULL',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (e) uploaded_by — users.id of the provider user
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venue_images' AND COLUMN_NAME = 'uploaded_by');
SET @ddl := IF(@exists = 0,
    'ALTER TABLE venue_images ADD COLUMN uploaded_by INT UNSIGNED NULL',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (f) original_filename
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venue_images' AND COLUMN_NAME = 'original_filename');
SET @ddl := IF(@exists = 0,
    'ALTER TABLE venue_images ADD COLUMN original_filename VARCHAR(255) NULL',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (g) file_size
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venue_images' AND COLUMN_NAME = 'file_size');
SET @ddl := IF(@exists = 0,
    'ALTER TABLE venue_images ADD COLUMN file_size INT UNSIGNED NULL',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (h) img_width
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venue_images' AND COLUMN_NAME = 'img_width');
SET @ddl := IF(@exists = 0,
    'ALTER TABLE venue_images ADD COLUMN img_width INT NULL',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (i) img_height
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venue_images' AND COLUMN_NAME = 'img_height');
SET @ddl := IF(@exists = 0,
    'ALTER TABLE venue_images ADD COLUMN img_height INT NULL',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (j) reviewed_by — set by U-P7b
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venue_images' AND COLUMN_NAME = 'reviewed_by');
SET @ddl := IF(@exists = 0,
    'ALTER TABLE venue_images ADD COLUMN reviewed_by INT NULL',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (k) reviewed_at — set by U-P7b
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venue_images' AND COLUMN_NAME = 'reviewed_at');
SET @ddl := IF(@exists = 0,
    'ALTER TABLE venue_images ADD COLUMN reviewed_at DATETIME NULL',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (l) review_note — set by U-P7b
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venue_images' AND COLUMN_NAME = 'review_note');
SET @ddl := IF(@exists = 0,
    'ALTER TABLE venue_images ADD COLUMN review_note TEXT NULL',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (m) review_reason — rejection reason, set by U-P7b
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venue_images' AND COLUMN_NAME = 'review_reason');
SET @ddl := IF(@exists = 0,
    'ALTER TABLE venue_images ADD COLUMN review_reason VARCHAR(80) NULL',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (n) index for the review-status filter (U-P7b queue + provider sections)
SET @exists := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venue_images' AND INDEX_NAME = 'idx_vimg_review_status');
SET @ddl := IF(@exists = 0,
    'ALTER TABLE venue_images ADD KEY idx_vimg_review_status (review_status)',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- End of 021_image_review.sql
