-- =============================================================================
-- 017 — venues: floor_area + floor_area_unit
-- Target database: sameraou_atv2   (MySQL 5.7, apply via phpMyAdmin)
--
-- Adds an optional floor-area/size to venues, with a sqm/sqft unit so the public
-- detail page can offer a unit toggle. venue_layout_capacity is unchanged (it
-- already exists — this migration only touches `venues`).
--
-- Idempotent on MySQL 5.7 (no ADD COLUMN IF NOT EXISTS): each change guarded via
-- information_schema so re-running is a no-op. Additive only — no data change.
-- =============================================================================

SET NAMES utf8mb4;

-- (a) floor_area — numeric size.
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venues' AND COLUMN_NAME = 'floor_area'
);
SET @ddl := IF(@exists = 0,
    'ALTER TABLE venues ADD COLUMN floor_area DECIMAL(10,2) NULL',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (b) floor_area_unit — the unit the number is stored in.
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venues' AND COLUMN_NAME = 'floor_area_unit'
);
SET @ddl := IF(@exists = 0,
    "ALTER TABLE venues ADD COLUMN floor_area_unit ENUM('sqm','sqft') NULL",
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- End of 017_venue_floor_area.sql
