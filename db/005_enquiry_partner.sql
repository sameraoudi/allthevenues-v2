-- =============================================================================
-- All The Venues — forward patch: enquiries.partner_id
-- Target database: sameraou_atv2 (already imported).
--
-- Partner-mode enquiries (/enquire?partner=id) record which partner the
-- enquiry is about, so the admin inbox can show "Enquiry about partner: {name}".
-- Folded into db/001_schema.sql for fresh imports; this ALTER brings an
-- already-imported DB up to date.
--
-- MySQL host (no MariaDB-only syntax). Run ONCE; re-running errors 1060
-- (duplicate column) — harmless.
-- =============================================================================

SET NAMES utf8mb4;

ALTER TABLE enquiries
    ADD COLUMN partner_id INT UNSIGNED DEFAULT NULL AFTER mode,
    ADD KEY idx_enquiries_partner (partner_id),
    ADD CONSTRAINT fk_enquiries_partner FOREIGN KEY (partner_id)
        REFERENCES partners (id) ON DELETE SET NULL ON UPDATE CASCADE;

-- End of 005_enquiry_partner.sql
