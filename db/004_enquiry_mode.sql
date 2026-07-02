-- =============================================================================
-- All The Venues — U3b-2 forward patch: enquiries.mode
-- Target database: sameraou_atv2 (already imported).
--
-- Records how each enquiry was made so the admin inbox can label it reliably
-- (venue enquiry / assisted / partner interest) instead of guessing from the
-- source URL. Folded into db/001_schema.sql for fresh imports; this ALTER
-- brings an already-imported DB up to date.
--
-- MySQL host (no MariaDB-only syntax). Run ONCE; re-running errors 1060
-- (duplicate column) — harmless.
-- =============================================================================

SET NAMES utf8mb4;

ALTER TABLE enquiries
    ADD COLUMN mode ENUM('venue','assisted','partner','general') NOT NULL DEFAULT 'general'
    AFTER source_page;

-- Backfill existing rows: any enquiry with a linked venue is a venue enquiry.
UPDATE enquiries e
    SET e.mode = 'venue'
    WHERE EXISTS (SELECT 1 FROM enquiry_venues ev WHERE ev.enquiry_id = e.id);

-- End of 004_enquiry_mode.sql
