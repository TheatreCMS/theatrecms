-- One row per generated thumbnail size for a `media` image row (see
-- src/Models/MediaVariant.php / src/Services/ImageVariantGenerator.php).
-- Doctrine already creates this table on a fresh schema. IF NOT EXISTS keeps
-- the transition migration safe to apply there and safe to re-run on upgrades.
CREATE TABLE IF NOT EXISTS `media_variants` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `media_id`   INT UNSIGNED NOT NULL,
    `size_name`  VARCHAR(50)  NOT NULL,
    `url`        VARCHAR(255) NOT NULL,
    `width`      INT UNSIGNED NOT NULL,
    `height`     INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `media_variants_media_size_unique` (`media_id`, `size_name`),
    CONSTRAINT `fk_media_variants_media` FOREIGN KEY (`media_id`)
        REFERENCES `media` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
