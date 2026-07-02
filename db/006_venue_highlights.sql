-- =============================================================================
-- All The Venues — forward patch: venues.highlights
-- Target database: sameraou_atv2 (already imported).
--
-- Curated "What makes it special" differentiators, edited in admin (U4a) and
-- sanitized on save. Replaces the previous best_for-derived highlights on the
-- public venue detail. Folded into db/001_schema.sql for fresh imports; this
-- ALTER brings an already-imported DB up to date.
--
-- MySQL host (no MariaDB-only syntax). Run ONCE; re-running errors 1060
-- (duplicate column) — harmless.
-- =============================================================================

SET NAMES utf8mb4;

ALTER TABLE venues
    ADD COLUMN highlights TEXT NULL AFTER best_for;

-- End of 006_venue_highlights.sql
