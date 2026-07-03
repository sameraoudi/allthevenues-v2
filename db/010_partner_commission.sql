-- =============================================================================
-- 010 — partners.commission_rate (lead commission, admin-only)
-- Target database: sameraou_atv2   (MySQL 5.7, apply via phpMyAdmin)
--
-- Captures whether/what commission a provider gives on leads. Tri-state:
--   NULL  = unknown / not set (the default for every existing row)
--   0.00  = confirmed none
--   >0    = the rate, as a percentage
-- DECIMAL(5,2) holds up to 999.99 — ample for a 0–100 percent. Admin-only data,
-- NEVER surfaced publicly.
--
-- Folded into db/001_schema.sql (partners, AFTER is_verified) for fresh installs.
-- Idempotent on MySQL 5.7 (no ADD COLUMN IF NOT EXISTS): guarded so re-running
-- is a no-op. New nullable column — no backfill (NULL = unknown is intended).
-- =============================================================================

SET NAMES utf8mb4;

-- Was the column already present before this run?
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'partners'
      AND COLUMN_NAME  = 'commission_rate'
);

-- Add the column only if missing.
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE partners ADD COLUMN commission_rate DECIMAL(5,2) NULL AFTER is_verified',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- End of 010_partner_commission.sql
