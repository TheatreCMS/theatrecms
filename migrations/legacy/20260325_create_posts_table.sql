-- 2026-03-25 add posts table

CREATE TABLE IF NOT EXISTS `posts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `title` VARCHAR(255) NOT NULL,
    `status` VARCHAR(32) NOT NULL,
    `content` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL,
    `modified_at` DATETIME NOT NULL,
    `published_at` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
