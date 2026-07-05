-- =============================================================================
-- 016 — venues + partners: legacy_id (U6 Phase-4 legacy URL 301 map)
-- Target database: sameraou_atv2   (MySQL 5.7, apply via phpMyAdmin)
--
-- Stores each row's LEGACY primary key so the old indexed URLs can 301 to the
-- new slugs by id (see lib/legacy_redirect.php):
--   * venues.legacy_id   = legacy `venues.id`     (URL: venue.php?venueid=<id>)
--   * partners.legacy_id  = legacy `providers.pid` (URL: provider.php?pid=<pid>)
-- Those legacy PKs ARE the ids used in the indexed URLs — that equality is the
-- whole point. Backfilled by db/backfill_legacy_ids.php.
--
-- A UNIQUE key allows many NULLs on MySQL (new rows keep legacy_id NULL) while
-- surfacing accidental duplicate assignments at backfill time.
--
-- Idempotent on MySQL 5.7 (no ADD COLUMN IF NOT EXISTS): each change guarded via
-- information_schema so re-running is a no-op. Additive only — no data change.
-- =============================================================================

SET NAMES utf8mb4;

-- (a) venues.legacy_id
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venues' AND COLUMN_NAME = 'legacy_id'
);
SET @ddl := IF(@exists = 0,
    'ALTER TABLE venues ADD COLUMN legacy_id INT UNSIGNED NULL',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (b) unique key on venues.legacy_id (NULLs allowed; blocks dupes).
SET @exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venues' AND INDEX_NAME = 'uq_venues_legacy'
);
SET @ddl := IF(@exists = 0,
    'ALTER TABLE venues ADD UNIQUE KEY uq_venues_legacy (legacy_id)',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (c) partners.legacy_id
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'partners' AND COLUMN_NAME = 'legacy_id'
);
SET @ddl := IF(@exists = 0,
    'ALTER TABLE partners ADD COLUMN legacy_id INT UNSIGNED NULL',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (d) unique key on partners.legacy_id.
SET @exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'partners' AND INDEX_NAME = 'uq_partners_legacy'
);
SET @ddl := IF(@exists = 0,
    'ALTER TABLE partners ADD UNIQUE KEY uq_partners_legacy (legacy_id)',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- End of 016_venue_partner_legacy_id.sql
