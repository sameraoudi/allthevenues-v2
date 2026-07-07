-- =============================================================================
-- 023 — venue delisting (Delist-1)
-- Target database: sameraou_atv2   (MySQL 5.7, apply via phpMyAdmin)
--
-- Reversible take-down of a PUBLISHED venue: the row, slug, leads and provenance
-- all stay — only visibility changes. A new venue status 'delisted' hides it from
-- every public surface (detail 404s; listings/search/event-type/sitemap already
-- filter status='published'); the slug stays reserved so re-listing restores the
-- same URL. Partners REQUEST a delist (admin-approved in Delist-2); re-list is
-- self-serve. A new change-request type 'delist' carries the request.
--
-- Idempotent on MySQL 5.7 (no ADD COLUMN / MODIFY ... IF NOT EXISTS): each ENUM
-- MODIFY is guarded by LOCATE() on the live COLUMN_TYPE and each ADD by an
-- information_schema COUNT — so a re-run is a clean no-op. Additive only; no
-- backfill (existing rows keep their status; the delist_* columns default NULL).
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- (A) venues.status ENUM — add 'delisted' (preserve existing values + default).
-- -----------------------------------------------------------------------------
SET @has := (
    SELECT LOCATE('delisted', COLUMN_TYPE) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venues' AND COLUMN_NAME = 'status'
);
SET @ddl := IF(@has = 0,
    "ALTER TABLE venues MODIFY COLUMN status ENUM('draft','pending','published','needs_changes','archived','delisted') NOT NULL DEFAULT 'draft'",
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- (B) venues — delist bookkeeping (all NULL until a delist is applied).
-- -----------------------------------------------------------------------------

-- (B1) delisted_at
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venues' AND COLUMN_NAME = 'delisted_at'
);
SET @ddl := IF(@exists = 0,
    'ALTER TABLE venues ADD COLUMN delisted_at DATETIME NULL',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (B2) delisted_by — the user (admin) who applied the delist; audit holds the trail.
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venues' AND COLUMN_NAME = 'delisted_by'
);
SET @ddl := IF(@exists = 0,
    'ALTER TABLE venues ADD COLUMN delisted_by INT UNSIGNED NULL',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (B3) delist_reason — the fixed reason code (renovation / not_operating / …).
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venues' AND COLUMN_NAME = 'delist_reason'
);
SET @ddl := IF(@exists = 0,
    'ALTER TABLE venues ADD COLUMN delist_reason VARCHAR(100) NULL',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (B4) delist_details — optional free-text note.
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venues' AND COLUMN_NAME = 'delist_details'
);
SET @ddl := IF(@exists = 0,
    'ALTER TABLE venues ADD COLUMN delist_details TEXT NULL',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- (C) venue_change_requests.type ENUM — add 'delist' (NOT NULL, no default).
-- -----------------------------------------------------------------------------
SET @has := (
    SELECT LOCATE('delist', COLUMN_TYPE) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venue_change_requests' AND COLUMN_NAME = 'type'
);
SET @ddl := IF(@has = 0,
    "ALTER TABLE venue_change_requests MODIFY COLUMN type ENUM('edit','new_venue','image','claim','delist') NOT NULL",
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- End of 023_delisting.sql
