ALTER TABLE `productions`
    ADD COLUMN `featured_image_id` INT UNSIGNED NULL AFTER `featured_image_url`,
    ADD CONSTRAINT `fk_productions_featured_image` FOREIGN KEY (`featured_image_id`) REFERENCES `images`(`id`) ON DELETE SET NULL;

ALTER TABLE `posts`
    ADD COLUMN `featured_image_id` INT UNSIGNED NULL AFTER `featured_image_url`,
    ADD CONSTRAINT `fk_posts_featured_image` FOREIGN KEY (`featured_image_id`) REFERENCES `images`(`id`) ON DELETE SET NULL;

ALTER TABLE `seasons`
    ADD COLUMN `featured_image_id` INT UNSIGNED NULL AFTER `featured_image_url`,
    ADD CONSTRAINT `fk_seasons_featured_image` FOREIGN KEY (`featured_image_id`) REFERENCES `images`(`id`) ON DELETE SET NULL;

ALTER TABLE `venues`
    ADD COLUMN `featured_image_id` INT UNSIGNED NULL AFTER `map_url`,
    ADD CONSTRAINT `fk_venues_featured_image` FOREIGN KEY (`featured_image_id`) REFERENCES `images`(`id`) ON DELETE SET NULL;
