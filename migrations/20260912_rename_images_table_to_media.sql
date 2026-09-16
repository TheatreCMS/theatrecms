-- Coordinated cutover: apply this with the release that maps the Media entity to
-- `media`. It is safe to re-run and is also a no-op for the table rename on a
-- fresh schema, where Doctrine creates `media` directly.
--
-- RENAME TABLE preserves all existing rows and, in MySQL/MariaDB, automatically
-- repoints every FK that references `images` (fk_productions_featured_image,
-- fk_posts_featured_image, fk_seasons_featured_image, fk_venues_featured_image)
-- at `media` -- no changes needed to those constraints or to the child tables.

SET @rename_images_to_media = IF(
    EXISTS (
        SELECT 1
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'images'
    )
    AND NOT EXISTS (
        SELECT 1
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'media'
    ),
    'RENAME TABLE `images` TO `media`',
    'DO 0'
);
PREPARE rename_images_to_media FROM @rename_images_to_media;
EXECUTE rename_images_to_media;
DEALLOCATE PREPARE rename_images_to_media;

SET @rename_images_url_index = IF(
    EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'media'
          AND INDEX_NAME = 'images_url_unique'
    )
    AND NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'media'
          AND INDEX_NAME = 'media_url_unique'
    ),
    'ALTER TABLE `media` RENAME INDEX `images_url_unique` TO `media_url_unique`',
    'DO 0'
);
PREPARE rename_images_url_index FROM @rename_images_url_index;
EXECUTE rename_images_url_index;
DEALLOCATE PREPARE rename_images_url_index;

-- Every existing row predates this feature and is an image; DEFAULT backfills
-- them in the same statement (MySQL/MariaDB populate existing rows with the
-- DEFAULT when adding a NOT NULL column).
SET @add_media_type_column = IF(
    NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'media'
          AND COLUMN_NAME = 'media_type'
    ),
    'ALTER TABLE `media` ADD COLUMN `media_type` VARCHAR(20) NOT NULL DEFAULT ''image'' AFTER `id`',
    'DO 0'
);
PREPARE add_media_type_column FROM @add_media_type_column;
EXECUTE add_media_type_column;
DEALLOCATE PREPARE add_media_type_column;

SET @add_media_type_index = IF(
    NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'media'
          AND INDEX_NAME = 'media_media_type_index'
    ),
    'ALTER TABLE `media` ADD INDEX `media_media_type_index` (`media_type`)',
    'DO 0'
);
PREPARE add_media_type_index FROM @add_media_type_index;
EXECUTE add_media_type_index;
DEALLOCATE PREPARE add_media_type_index;
