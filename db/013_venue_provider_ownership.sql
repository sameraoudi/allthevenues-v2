-- =============================================================================
-- 013 — venues: provider-ownership provenance (around partner_id)
-- Target database: sameraou_atv2   (MySQL 5.7, apply via phpMyAdmin)
--
-- venues.partner_id already links a venue to its managing provider. This adds
-- provenance so we know HOW a venue became provider-managed and when/by whom —
-- the foundation the future provider portal (#3) keys on:
--   * management_source    — how the ownership link was established;
--   * provider_assigned_at — when it was assigned;
--   * provider_assigned_by — which staff user assigned it (soft ref to users.id,
--                            set by app code; no FK — historical/provenance field).
-- "managed_by_provider" stays DERIVED (partner_id IS NOT NULL) — not a column.
--
-- Folded into db/001_schema.sql (venues, AFTER partner_id) for fresh installs.
-- Idempotent on MySQL 5.7 (no ADD COLUMN IF NOT EXISTS): each change guarded via
-- information_schema so re-running is a no-op. The backfill runs ONLY on first
-- creation of management_source, so re-runs NEVER clobber later admin-set values.
-- =============================================================================

SET NAMES utf8mb4;

-- Was management_source already present before this run? (Computed BEFORE the
-- ALTER so the backfill can be gated on first creation only.)
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'venues'
      AND COLUMN_NAME  = 'management_source'
);

-- (a) management_source — how the ownership link was established.
SET @ddl := IF(@col_exists = 0,
    "ALTER TABLE venues ADD COLUMN management_source ENUM('unassigned','admin_assigned','provider_created','provider_claimed','legacy_import') NOT NULL DEFAULT 'unassigned' AFTER partner_id",
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (b) provider_assigned_at — when the provider link was assigned.
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venues' AND COLUMN_NAME = 'provider_assigned_at'
);
SET @ddl := IF(@exists = 0,
    'ALTER TABLE venues ADD COLUMN provider_assigned_at DATETIME NULL AFTER management_source',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (c) provider_assigned_by — staff user who assigned it (soft ref to users.id).
SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venues' AND COLUMN_NAME = 'provider_assigned_by'
);
SET @ddl := IF(@exists = 0,
    'ALTER TABLE venues ADD COLUMN provider_assigned_by INT UNSIGNED NULL AFTER provider_assigned_at',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (d) Backfill ONLY on first creation, so re-runs can't clobber admin edits.
-- Existing venues with a provider came from the legacy migration; NULL-partner
-- venues stay 'unassigned'.
SET @bf := IF(@col_exists = 0,
    "UPDATE venues SET management_source = 'legacy_import' WHERE partner_id IS NOT NULL",
    'DO 0');
PREPARE stmt FROM @bf; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- End of 013_venue_provider_ownership.sql
