-- 2026-07-09 rename seasons.hero_image_url to featured_image_url and make it nullable,
-- bringing Season in line with Post/Production's featured image handling.

ALTER TABLE `seasons`
    CHANGE COLUMN `hero_image_url` `featured_image_url` VARCHAR(255) NULL;
