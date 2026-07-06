-- =============================================================================
-- 019 — venue_change_requests (#3 U-P1: provider-portal request/approval)
-- Target database: sameraou_atv2   (MySQL 5.7, apply via phpMyAdmin)
--
-- One row per pending provider action (edit of a sensitive field, new-venue
-- submission, image upload, or ownership claim). The provider-facing portal
-- writes these; admin reviews/approves. proposed_changes_json holds the field
-- diff / new-venue payload / image ref / claim target (JSON, like audit_log's
-- *_json columns). venue_id is NULL for a new-venue submission. FK + type
-- conventions mirror db/001_schema.sql (INT UNSIGNED FK cols; named fk_*
-- constraints). No application code depends on this yet (U-P5) — this unit is
-- schema-only and inert.
--
-- CREATE TABLE IF NOT EXISTS is MySQL-5.7-safe + idempotent (re-running is a
-- no-op). Additive only; empty on create — no data change.
-- =============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS venue_change_requests (
    id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    venue_id              INT UNSIGNED DEFAULT NULL,           -- NULL for a new-venue submission
    partner_id            INT UNSIGNED NOT NULL,               -- owning provider (scope key)
    submitted_by          INT UNSIGNED DEFAULT NULL,           -- users.id of the partner user
    type                  ENUM('edit','new_venue','image','claim') NOT NULL,
    proposed_changes_json JSON         DEFAULT NULL,
    status                ENUM('pending','approved','rejected','needs_changes','withdrawn')
                              NOT NULL DEFAULT 'pending',
    review_note           TEXT         DEFAULT NULL,
    reviewed_by           INT UNSIGNED DEFAULT NULL,           -- admin/editor users.id
    reviewed_at           DATETIME     DEFAULT NULL,
    created_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_vcr_partner_status (partner_id, status),
    KEY idx_vcr_venue (venue_id),
    KEY idx_vcr_status_created (status, created_at),
    KEY idx_vcr_type (type),
    CONSTRAINT fk_vcr_venue    FOREIGN KEY (venue_id)     REFERENCES venues (id)   ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT fk_vcr_partner  FOREIGN KEY (partner_id)   REFERENCES partners (id) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT fk_vcr_user     FOREIGN KEY (submitted_by) REFERENCES users (id)    ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_vcr_reviewer FOREIGN KEY (reviewed_by)  REFERENCES users (id)    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- End of 019_venue_change_requests.sql
