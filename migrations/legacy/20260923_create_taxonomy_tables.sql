-- 2026-09-23 add taxonomy tables: terms and term_relationships
--
-- Taxonomies themselves (genre, post_category, ...) are registered in code via
-- register_taxonomy(); only their terms are stored. `terms.taxonomy` holds the
-- registry key, and slugs are unique per taxonomy rather than table-wide.
--
-- `term_relationships.content_id` is intentionally not FK-constrained since it
-- references a different table depending on `content_type` — same precedent as
-- `content_meta.content_id` and `menu_items.target_id`. `term_id` is a real FK
-- so deleting a term removes its assignments.
-- Doctrine already creates these tables on a fresh schema. IF NOT EXISTS keeps
-- the migration safe to apply there and safe to re-run on upgrades.

CREATE TABLE IF NOT EXISTS `terms` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `taxonomy`      VARCHAR(32)  NOT NULL,
    `name`          VARCHAR(255) NOT NULL,
    `slug`          VARCHAR(191) NOT NULL,
    `description`   LONGTEXT     NULL,
    `created_at`    DATETIME     NOT NULL,
    `modified_at`   DATETIME     NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_terms_taxonomy_slug` (`taxonomy`, `slug`),
    KEY `idx_terms_taxonomy` (`taxonomy`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `term_relationships` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `term_id`       INT UNSIGNED NOT NULL,
    `content_type`  VARCHAR(32)  NOT NULL,
    `content_id`    INT UNSIGNED NOT NULL,
    `position`      INT          NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_term_rel` (`term_id`, `content_type`, `content_id`),
    KEY `idx_term_rel_content` (`content_type`, `content_id`),
    CONSTRAINT `FK_TERM_RELATIONSHIPS_TERM` FOREIGN KEY (`term_id`) REFERENCES `terms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
