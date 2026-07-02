-- =============================================================================
-- All The Venues — Phase-1 taxonomy seed (U1a)
-- Target database: sameraou_atv2
-- Spec: docs/ATV-SCHEMA.md §1 (event_types 15, venue_types 15, emirates 8).
--
-- Fixed ids so FK references (venue_type_id, event_type_id, emirate_id) are
-- stable across environments. Re-runnable: INSERT ... ON DUPLICATE KEY UPDATE
-- refreshes name/slug/sort_order/active without creating duplicates.
--
-- Apply AFTER 001_schema.sql, via phpMyAdmin against sameraou_atv2.
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- event_types — 15 (order = sort_order)
-- -----------------------------------------------------------------------------
INSERT INTO event_types (id, name, slug, sort_order, active) VALUES
    ( 1, 'Wedding',           'wedding',           1,  1),
    ( 2, 'Engagement',        'engagement',        2,  1),
    ( 3, 'Corporate Event',   'corporate-event',   3,  1),
    ( 4, 'Conference',        'conference',        4,  1),
    ( 5, 'Meeting',           'meeting',           5,  1),
    ( 6, 'Training',          'training',          6,  1),
    ( 7, 'Product Launch',    'product-launch',    7,  1),
    ( 8, 'Gala Dinner',       'gala-dinner',       8,  1),
    ( 9, 'Private Party',     'private-party',     9,  1),
    (10, 'Birthday',          'birthday',          10, 1),
    (11, 'Outdoor Event',     'outdoor-event',     11, 1),
    (12, 'Yacht Event',       'yacht-event',       12, 1),
    (13, 'Exhibition',        'exhibition',        13, 1),
    (14, 'Networking Event',  'networking-event',  14, 1),
    (15, 'Other',             'other',             15, 1)
ON DUPLICATE KEY UPDATE
    name       = VALUES(name),
    slug       = VALUES(slug),
    sort_order = VALUES(sort_order),
    active     = VALUES(active);

-- -----------------------------------------------------------------------------
-- venue_types — 15 (order = sort_order)
-- -----------------------------------------------------------------------------
INSERT INTO venue_types (id, name, slug, sort_order, active) VALUES
    ( 1, 'Hotel Ballroom',       'hotel-ballroom',       1,  1),
    ( 2, 'Banquet Hall',         'banquet-hall',         2,  1),
    ( 3, 'Restaurant',           'restaurant',           3,  1),
    ( 4, 'Private Dining Room',  'private-dining-room',  4,  1),
    ( 5, 'Outdoor Venue',        'outdoor-venue',        5,  1),
    ( 6, 'Rooftop Venue',        'rooftop-venue',        6,  1),
    ( 7, 'Beach Venue',          'beach-venue',          7,  1),
    ( 8, 'Resort',               'resort',               8,  1),
    ( 9, 'Conference Centre',    'conference-centre',    9,  1),
    (10, 'Meeting Room',         'meeting-room',         10, 1),
    (11, 'Yacht',                'yacht',                11, 1),
    (12, 'Island Venue',         'island-venue',         12, 1),
    (13, 'Garden Venue',         'garden-venue',         13, 1),
    (14, 'Lounge',               'lounge',               14, 1),
    (15, 'Unique Venue',         'unique-venue',         15, 1)
ON DUPLICATE KEY UPDATE
    name       = VALUES(name),
    slug       = VALUES(slug),
    sort_order = VALUES(sort_order),
    active     = VALUES(active);

-- -----------------------------------------------------------------------------
-- emirates — 8 (order = sort_order)
-- -----------------------------------------------------------------------------
INSERT INTO emirates (id, name, slug, sort_order, active) VALUES
    (1, 'Dubai',           'dubai',           1, 1),
    (2, 'Abu Dhabi',       'abu-dhabi',       2, 1),
    (3, 'Sharjah',         'sharjah',         3, 1),
    (4, 'Ajman',           'ajman',           4, 1),
    (5, 'Ras Al Khaimah',  'ras-al-khaimah',  5, 1),
    (6, 'Fujairah',        'fujairah',        6, 1),
    (7, 'Umm Al Quwain',   'umm-al-quwain',   7, 1),
    (8, 'Al Ain',          'al-ain',          8, 1)
ON DUPLICATE KEY UPDATE
    name       = VALUES(name),
    slug       = VALUES(slug),
    sort_order = VALUES(sort_order),
    active     = VALUES(active);

-- End of 002_seed_taxonomy.sql
