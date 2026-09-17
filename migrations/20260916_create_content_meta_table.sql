-- Generic key/value metadata store for any content type (productions, works,
-- people, posts, pages, seasons, venues, events, sponsors, and future
-- theme/plugin-registered types), keyed by (content_type, content_id) rather
-- than a dedicated *_meta table per type. `content_id` is intentionally not
-- FK-constrained since it references a different table depending on
-- `content_type` — same precedent as `menu_items.target_id` (see
-- migrations/20260702_create_menus_tables.sql).
-- Doctrine already creates this table on a fresh schema. IF NOT EXISTS keeps
-- the transition migration safe to apply there and safe to re-run on upgrades.
CREATE TABLE IF NOT EXISTS `content_meta` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `content_type`  VARCHAR(32)  NOT NULL,
    `content_id`    INT UNSIGNED NOT NULL,
    `meta_key`      VARCHAR(191) NOT NULL,
    `meta_value`    LONGTEXT     NULL,
    `created_at`    DATETIME     NOT NULL,
    `modified_at`   DATETIME     NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_content_meta_key` (`content_type`, `content_id`, `meta_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
