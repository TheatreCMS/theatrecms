CREATE TABLE `images` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `url` VARCHAR(255) NOT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `original_filename` VARCHAR(255) NULL,
    `mime_type` VARCHAR(100) NULL,
    `size_bytes` INT UNSIGNED NULL,
    `width` INT UNSIGNED NULL,
    `height` INT UNSIGNED NULL,
    `alt_text` VARCHAR(255) NULL,
    `uploaded_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `images_url_unique` (`url`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
