-- =============================================================================
-- All The Venues — U1a forward patch (U1a-fix)
-- Target database: sameraou_atv2 (already imported from 001 + 002).
--
-- Brings the LIVE DB up to the FINAL spec (docs/ATV-SCHEMA.md) without a
-- full re-import. Applies ONLY the two finalized items missing from the
-- first import:
--   1. venues.indoor_outdoor ENUM (was absent).
--   2. venue_types Villa (16) + Warehouse (17) (was 15 rows).
--
-- The cPanel host is MySQL (NOT MariaDB), so MariaDB-only syntax such as
-- ADD COLUMN IF NOT EXISTS is unavailable.
--
-- Re-run behaviour:
--   * The ALTER runs ONCE (see note below). Re-running errors 1060
--     (duplicate column) — harmless; the column is already there.
--   * The venue_types INSERT ... ON DUPLICATE KEY UPDATE is a no-op refresh
--     on re-run (standard MySQL syntax).
--
-- venues is empty, so no backfill is needed; the NOT NULL DEFAULT 'indoor'
-- applies to future rows only.
--
-- Apply AFTER 001 + 002 have been imported, via phpMyAdmin against
-- sameraou_atv2.
-- =============================================================================

SET NAMES utf8mb4;

-- 1. Add the missing venues.indoor_outdoor column (after venue_type_id).
--    Run ONCE; MySQL host (no ADD COLUMN IF NOT EXISTS). Re-running errors
--    1060 (duplicate column) — harmless.
ALTER TABLE venues
    ADD COLUMN indoor_outdoor
        ENUM('indoor','outdoor','both') NOT NULL DEFAULT 'indoor'
        AFTER venue_type_id;

-- 2. Add Villa + Warehouse to venue_types (→ 17 total). Matches db/002's
--    fixed-id / VALUES()-style convention; standard MySQL syntax.
INSERT INTO venue_types (id, name, slug, sort_order, active) VALUES
    (16, 'Villa',      'villa',      16, 1),
    (17, 'Warehouse',  'warehouse',  17, 1)
ON DUPLICATE KEY UPDATE
    name       = VALUES(name),
    slug       = VALUES(slug),
    sort_order = VALUES(sort_order),
    active     = VALUES(active);

-- End of 003_u1a_fixups.sql
