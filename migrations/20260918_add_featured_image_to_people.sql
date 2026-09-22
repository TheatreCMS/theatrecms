ALTER TABLE `people`
    ADD COLUMN `featured_image_id` INT UNSIGNED NULL AFTER `headshot_url`,
    ADD CONSTRAINT `fk_people_featured_image` FOREIGN KEY (`featured_image_id`) REFERENCES `media`(`id`) ON DELETE SET NULL;
