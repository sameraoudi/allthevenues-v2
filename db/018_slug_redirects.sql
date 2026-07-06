-- =============================================================================
-- 018 — slug_redirects (#10: SEO-safe 301s on venue/provider slug change)
-- Target database: sameraou_atv2   (MySQL 5.7, apply via phpMyAdmin)
--
-- When a venue/provider slug changes in admin, the OLD pretty URL should 301 to
-- the current one (only when the target is currently published/approved) instead
-- of 404ing. Rows point at the ENTITY id (not the next slug), so chained renames
-- A→B→C resolve in a single hop. Independent of legacy_id (db/016) — that keys on
-- the old numeric URL; this keys on the old pretty slug.
--
-- CREATE TABLE IF NOT EXISTS is MySQL-5.7-safe + idempotent (re-running is a
-- no-op). Additive only — no data change.
-- =============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS slug_redirects (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  entity_type ENUM('venue','provider') NOT NULL,
  old_slug    VARCHAR(191) NOT NULL,
  entity_id   INT UNSIGNED NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_slug_redirect (entity_type, old_slug),
  KEY idx_slug_redirect_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- End of 018_slug_redirects.sql
