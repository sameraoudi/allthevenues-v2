-- =============================================================================
-- 024 — partner_emails (admin → partner templated email + send log)
-- Target database: sameraou_atv2   (MySQL 5.7, apply via phpMyAdmin FIRST)
--
-- Logs every email an admin sends to a venue partner from the Providers page:
-- the chosen template, recipient (+ optional cc/bcc), the exact subject/body
-- that went out (plain text + branded HTML), who sent it, delivery status, the
-- captured message-id / error, and optional venue/enquiry context for later.
-- Surfaced as "Email history" on the partner detail.
--
-- Idempotent + additive: CREATE TABLE IF NOT EXISTS (re-run = clean no-op). No
-- backfill. InnoDB/utf8mb4, MySQL-5.7-safe (no 8.0-only clauses). FKs: partner_id
-- → partners.id ON DELETE CASCADE (drop a partner ⇒ drop its email log);
-- sent_by → users.id ON DELETE SET NULL (keep the log if the admin is removed).
-- =============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS partner_emails (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  partner_id          INT UNSIGNED NOT NULL,
  template_key        VARCHAR(40)  NULL,
  recipient_email     VARCHAR(255) NOT NULL,
  cc                  VARCHAR(500) NULL,
  bcc                 VARCHAR(500) NULL,
  subject             VARCHAR(255) NOT NULL,
  body_html           MEDIUMTEXT   NULL,
  body_text           MEDIUMTEXT   NULL,
  sent_by             INT UNSIGNED NULL,
  status              ENUM('draft','sent','failed') NOT NULL DEFAULT 'sent',
  error_message       TEXT         NULL,
  message_id          VARCHAR(255) NULL,
  related_venue_id    INT UNSIGNED NULL,
  related_enquiry_id  INT UNSIGNED NULL,
  sent_at             DATETIME     NULL,
  created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pe_partner (partner_id, sent_at),
  CONSTRAINT fk_pe_partner FOREIGN KEY (partner_id) REFERENCES partners (id) ON DELETE CASCADE,
  CONSTRAINT fk_pe_sent_by FOREIGN KEY (sent_by)    REFERENCES users (id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- End of 024_partner_emails.sql
