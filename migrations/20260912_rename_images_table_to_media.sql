-- Coordinated cutover: apply this immediately before deploying the release that
-- renames Image -> Media / ImageRepository -> MediaRepository (see
-- src/Models/Media.php). Unlike the phased featured_image_url -> featured_image_id
-- migration, this is NOT safe to pre-apply ahead of a rolling deploy: the old
-- Image entity maps to table `images`, the new Media entity maps to table `media`,
-- and only one of those table names exists at a time after this file runs.
--
-- RENAME TABLE preserves all existing rows and, in MySQL/MariaDB, automatically
-- repoints every FK that references `images` (fk_productions_featured_image,
-- fk_posts_featured_image, fk_seasons_featured_image, fk_venues_featured_image)
-- at `media` -- no changes needed to those constraints or to the child tables.
--
-- Skip this file on a genuinely fresh install: orm:schema-tool:create builds
-- the table directly from the Media entity, already named `media` with a
-- media_type column.

RENAME TABLE `images` TO `media`;

ALTER TABLE `media`
    RENAME INDEX `images_url_unique` TO `media_url_unique`;

-- Every existing row predates this feature and is an image; DEFAULT backfills
-- them in the same statement (MySQL/MariaDB populate existing rows with the
-- DEFAULT when adding a NOT NULL column).
ALTER TABLE `media`
    ADD COLUMN `media_type` VARCHAR(20) NOT NULL DEFAULT 'image' AFTER `id`,
    ADD INDEX `media_media_type_index` (`media_type`);
