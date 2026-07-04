-- =============================================================================
-- All The Venues — Phase-1 schema (U1a)
-- Target database: sameraou_atv2
-- Spec: docs/ATV-SCHEMA.md §1–§4 (authoritative).
--
-- Conventions:
--   * ENGINE=InnoDB, CHARSET=utf8mb4, COLLATE=utf8mb4_unicode_ci throughout.
--   * All PKs and FK columns are INT UNSIGNED for consistent FK typing.
--   * slug columns are VARCHAR(191) so a UNIQUE index fits utf8mb4 limits.
--   * FK ON DELETE: CASCADE for owned children (images, layouts, join rows);
--     SET NULL for optional references (taxonomy, partner) so deleting a
--     lookup row never destroys catalogue rows.
--   * Tables created in dependency order; safe to run top-to-bottom.
--   * CREATE TABLE IF NOT EXISTS for re-run friendliness.
--
-- Apply via phpMyAdmin against sameraou_atv2 (localhost-only on the server).
-- =============================================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 1;

-- -----------------------------------------------------------------------------
-- §1  Taxonomy / reference tables (seeded in 002_seed_taxonomy.sql)
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS event_types (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(191) NOT NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    active      TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_event_types_slug (slug),
    KEY idx_event_types_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS venue_types (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(191) NOT NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    active      TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_venue_types_slug (slug),
    KEY idx_venue_types_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS emirates (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(191) NOT NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    active      TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_emirates_slug (slug),
    KEY idx_emirates_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- §2  Core catalogue tables
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS partners (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug          VARCHAR(191) NOT NULL,
    org_name      VARCHAR(255) NOT NULL,
    partner_group VARCHAR(100) DEFAULT NULL,
    contact_name  VARCHAR(255) DEFAULT NULL,
    email         VARCHAR(255) DEFAULT NULL,
    phone         VARCHAR(50)  DEFAULT NULL,
    website       VARCHAR(255) DEFAULT NULL,
    emirate_id    INT UNSIGNED DEFAULT NULL,
    city_text     VARCHAR(150) DEFAULT NULL,
    about         TEXT         DEFAULT NULL,          -- sanitized HTML
    logo_path     VARCHAR(255) DEFAULT NULL,
    cover_image_path VARCHAR(255) DEFAULT NULL,
    cover_thumb_path VARCHAR(255) DEFAULT NULL,
    cover_image_alt  VARCHAR(255) DEFAULT NULL,
    status        ENUM('draft','pending','approved','suspended') NOT NULL DEFAULT 'draft',
    is_featured   TINYINT(1)   NOT NULL DEFAULT 0,
    is_verified   TINYINT(1)   NOT NULL DEFAULT 0,
    commission_rate DECIMAL(5,2) DEFAULT NULL,
    notes         TEXT         DEFAULT NULL,          -- admin-only
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at   DATETIME     DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_partners_slug (slug),
    KEY idx_partners_emirate (emirate_id),
    KEY idx_partners_status (status),
    KEY idx_partners_featured (is_featured),
    CONSTRAINT fk_partners_emirate FOREIGN KEY (emirate_id)
        REFERENCES emirates (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS venues (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug           VARCHAR(191) NOT NULL,
    partner_id     INT UNSIGNED DEFAULT NULL,
    management_source    ENUM('unassigned','admin_assigned','provider_created','provider_claimed','legacy_import') NOT NULL DEFAULT 'unassigned',
    provider_assigned_at DATETIME     DEFAULT NULL,
    provider_assigned_by INT UNSIGNED DEFAULT NULL,
    name           VARCHAR(255) NOT NULL,
    status         ENUM('draft','pending','published','needs_changes','archived') NOT NULL DEFAULT 'draft',
    is_featured    TINYINT(1)   NOT NULL DEFAULT 0,
    is_verified    TINYINT(1)   NOT NULL DEFAULT 0,
    -- Classification
    venue_type_id  INT UNSIGNED DEFAULT NULL,
    indoor_outdoor ENUM('indoor','outdoor','both') NOT NULL DEFAULT 'indoor',
    emirate_id     INT UNSIGNED DEFAULT NULL,
    area           VARCHAR(150) DEFAULT NULL,
    address        VARCHAR(255) DEFAULT NULL,
    map_embed      TEXT         DEFAULT NULL,
    lat            DECIMAL(10,7) DEFAULT NULL,
    lng            DECIMAL(10,7) DEFAULT NULL,
    contact_name   VARCHAR(255) DEFAULT NULL,
    contact_email  VARCHAR(255) DEFAULT NULL,
    contact_phone  VARCHAR(50)  DEFAULT NULL,
    -- Content (sanitized HTML where rich text)
    description    TEXT         DEFAULT NULL,
    best_for       TEXT         DEFAULT NULL,
    highlights     TEXT         DEFAULT NULL,          -- curated "what makes it special" (admin-edited, sanitized)
    facilities     TEXT         DEFAULT NULL,
    food_beverage  TEXT         DEFAULT NULL,
    av_support     TEXT         DEFAULT NULL,
    restrictions   TEXT         DEFAULT NULL,
    packages       TEXT         DEFAULT NULL,
    special_offer  TEXT         DEFAULT NULL,
    atv_special_offer TEXT      DEFAULT NULL,
    video_url      VARCHAR(255) DEFAULT NULL,
    website        VARCHAR(255) DEFAULT NULL,         -- venue's own site (backfilled from legacy; see db/007)
    -- Capacity / price
    capacity_max   INT UNSIGNED DEFAULT NULL,
    capacity_min   INT UNSIGNED DEFAULT NULL,
    pricing_level  VARCHAR(50)  DEFAULT NULL,         -- derived; budget label
    minimum_spend  DECIMAL(12,2) DEFAULT NULL,
    -- ATV editorial
    atv_rating     DECIMAL(2,1) DEFAULT NULL,
    atv_review     TEXT         DEFAULT NULL,         -- sanitized
    main_image     VARCHAR(255) DEFAULT NULL,
    created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    published_at   DATETIME     DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_venues_slug (slug),
    KEY idx_venues_partner (partner_id),
    KEY idx_venues_type (venue_type_id),
    KEY idx_venues_emirate (emirate_id),
    KEY idx_venues_status (status),
    KEY idx_venues_featured (is_featured),
    CONSTRAINT fk_venues_partner FOREIGN KEY (partner_id)
        REFERENCES partners (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_venues_type FOREIGN KEY (venue_type_id)
        REFERENCES venue_types (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_venues_emirate FOREIGN KEY (emirate_id)
        REFERENCES emirates (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS venue_layout_capacity (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    venue_id     INT UNSIGNED NOT NULL,
    layout_type  ENUM('Banquet','Reception','Theatre','Classroom','Cabaret','Boardroom','U-shape','H-shape') NOT NULL,
    capacity     INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_venue_layout (venue_id, layout_type),
    KEY idx_layout_venue (venue_id),
    CONSTRAINT fk_layout_venue FOREIGN KEY (venue_id)
        REFERENCES venues (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS venue_event_types (
    venue_id      INT UNSIGNED NOT NULL,
    event_type_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (venue_id, event_type_id),
    KEY idx_vet_event (event_type_id),
    CONSTRAINT fk_vet_venue FOREIGN KEY (venue_id)
        REFERENCES venues (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_vet_event FOREIGN KEY (event_type_id)
        REFERENCES event_types (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS venue_images (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    venue_id    INT UNSIGNED NOT NULL,
    file_path   VARCHAR(255) NOT NULL,
    thumb_path  VARCHAR(255) DEFAULT NULL,
    alt_text    VARCHAR(255) DEFAULT NULL,
    is_primary  TINYINT(1)   NOT NULL DEFAULT 0,
    sort_order  INT          NOT NULL DEFAULT 0,
    status      ENUM('active','hidden') NOT NULL DEFAULT 'active',
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_images_venue (venue_id),
    KEY idx_images_primary (venue_id, is_primary),
    CONSTRAINT fk_images_venue FOREIGN KEY (venue_id)
        REFERENCES venues (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS venue_documents (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    venue_id    INT UNSIGNED NOT NULL,
    title       VARCHAR(255) DEFAULT NULL,
    file_path   VARCHAR(255) NOT NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_documents_venue (venue_id),
    CONSTRAINT fk_documents_venue FOREIGN KEY (venue_id)
        REFERENCES venues (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- §3  People / access
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(255) NOT NULL,
    email         VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('admin','editor','partner') NOT NULL DEFAULT 'editor',
    status        ENUM('active','disabled') NOT NULL DEFAULT 'active',
    partner_id    INT UNSIGNED DEFAULT NULL,           -- null for staff
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at DATETIME     DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role),
    KEY idx_users_partner (partner_id),
    CONSTRAINT fk_users_partner FOREIGN KEY (partner_id)
        REFERENCES partners (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id        INT UNSIGNED DEFAULT NULL,
    action         VARCHAR(100) NOT NULL,
    entity_type    VARCHAR(100) DEFAULT NULL,
    entity_id      INT UNSIGNED DEFAULT NULL,
    old_value_json JSON         DEFAULT NULL,
    new_value_json JSON         DEFAULT NULL,
    ip_address     VARCHAR(45)  DEFAULT NULL,           -- IPv4/IPv6
    created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_user (user_id),
    KEY idx_audit_entity (entity_type, entity_id),
    KEY idx_audit_created (created_at),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- §4  Enquiries / leads  (schema now; data migrates in U3)
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS enquiries (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reference        VARCHAR(30)  NOT NULL,             -- generated, human-facing
    name             VARCHAR(255) DEFAULT NULL,
    email            VARCHAR(255) DEFAULT NULL,
    phone            VARCHAR(50)  DEFAULT NULL,
    company          VARCHAR(255) DEFAULT NULL,
    event_type_id    INT UNSIGNED DEFAULT NULL,
    event_date       DATE         DEFAULT NULL,
    date_flexibility VARCHAR(100) DEFAULT NULL,
    emirate_id       INT UNSIGNED DEFAULT NULL,
    city_pref        VARCHAR(150) DEFAULT NULL,
    guest_count      VARCHAR(30)  DEFAULT NULL,         -- range label (app config)
    budget_range     VARCHAR(50)  DEFAULT NULL,         -- range label (app config)
    venue_preference VARCHAR(255) DEFAULT NULL,
    indoor_outdoor   VARCHAR(30)  DEFAULT NULL,
    fb_requirements  TEXT         DEFAULT NULL,
    av_requirements  TEXT         DEFAULT NULL,
    notes            TEXT         DEFAULT NULL,
    consent_to_share TINYINT(1)   NOT NULL DEFAULT 0,
    source_page      VARCHAR(255) DEFAULT NULL,
    mode             ENUM('venue','assisted','partner','general','partner_signup') NOT NULL DEFAULT 'general',
    partner_id       INT UNSIGNED DEFAULT NULL,          -- partner-mode enquiries (?partner=id)
    provider_type    VARCHAR(50)  DEFAULT NULL,          -- partner_signup: provider category
    website          VARCHAR(255) DEFAULT NULL,          -- partner_signup: provider site
    venues_managed   INT UNSIGNED DEFAULT NULL,          -- partner_signup: # venues managed
    status           ENUM('new','reviewed','forwarded','accepted','contacted','won','lost','closed','spam') NOT NULL DEFAULT 'new',
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_enquiries_reference (reference),
    KEY idx_enquiries_email (email),
    KEY idx_enquiries_status (status),
    KEY idx_enquiries_event_type (event_type_id),
    KEY idx_enquiries_emirate (emirate_id),
    KEY idx_enquiries_partner (partner_id),
    KEY idx_enquiries_created (created_at),
    CONSTRAINT fk_enquiries_event_type FOREIGN KEY (event_type_id)
        REFERENCES event_types (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_enquiries_emirate FOREIGN KEY (emirate_id)
        REFERENCES emirates (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_enquiries_partner FOREIGN KEY (partner_id)
        REFERENCES partners (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS enquiry_venues (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    enquiry_id  INT UNSIGNED NOT NULL,
    venue_id    INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_enquiry_venue (enquiry_id, venue_id),
    KEY idx_ev_venue (venue_id),
    CONSTRAINT fk_ev_enquiry FOREIGN KEY (enquiry_id)
        REFERENCES enquiries (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ev_venue FOREIGN KEY (venue_id)
        REFERENCES venues (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lead_routing (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    enquiry_id      INT UNSIGNED NOT NULL,
    venue_id        INT UNSIGNED DEFAULT NULL,
    partner_id      INT UNSIGNED DEFAULT NULL,
    routed_to_email VARCHAR(255) DEFAULT NULL,
    status          ENUM('pending','sent','accepted','declined','expired') NOT NULL DEFAULT 'pending',
    routed_at       DATETIME     DEFAULT NULL,
    response_at     DATETIME     DEFAULT NULL,
    admin_note      TEXT         DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_routing_enquiry (enquiry_id),
    KEY idx_routing_venue (venue_id),
    KEY idx_routing_partner (partner_id),
    KEY idx_routing_status (status),
    CONSTRAINT fk_routing_enquiry FOREIGN KEY (enquiry_id)
        REFERENCES enquiries (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_routing_venue FOREIGN KEY (venue_id)
        REFERENCES venues (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_routing_partner FOREIGN KEY (partner_id)
        REFERENCES partners (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- End of 001_schema.sql
