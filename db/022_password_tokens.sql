-- =============================================================================
-- 022 — password_tokens (#3 U-P9a: partner onboarding + set-password flow)
-- Target database: sameraou_atv2   (MySQL 5.7, apply via phpMyAdmin)
--
-- One-time, hashed tokens for account setup ('invite') and future staff password
-- resets ('reset'). The RAW token is emailed once and never stored — only its
-- SHA-256 hex (token_hash) is kept, so a DB read can't reconstruct a usable link.
-- A token is spent by setting used_at; expiry is a hard 48h from creation (set by
-- the app). CREATE TABLE IF NOT EXISTS → MySQL-5.7-safe + idempotent (re-run =
-- no-op). Additive; empty on create.
-- =============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS password_tokens (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    purpose     ENUM('invite','reset') NOT NULL DEFAULT 'invite',
    token_hash  CHAR(64)     NOT NULL,                 -- SHA-256 hex of the raw token
    created_by  INT UNSIGNED DEFAULT NULL,             -- admin who issued it
    sent_to     VARCHAR(255) DEFAULT NULL,             -- email the link went to
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at  DATETIME     NOT NULL,
    used_at     DATETIME     DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pt_hash (token_hash),
    KEY idx_pt_user (user_id, purpose),
    KEY idx_pt_expires (expires_at),
    CONSTRAINT fk_pt_user    FOREIGN KEY (user_id)    REFERENCES users (id) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT fk_pt_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- End of 022_password_tokens.sql
