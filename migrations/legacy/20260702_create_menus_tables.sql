-- 2026-07-02 add menus and menu_items tables

CREATE TABLE IF NOT EXISTS `menus` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `location` VARCHAR(100) NULL,
    `created_at` DATETIME NOT NULL,
    `modified_at` DATETIME NOT NULL,
    UNIQUE KEY `UNIQ_MENUS_LOCATION` (`location`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `menu_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `menu_id` INT UNSIGNED NOT NULL,
    `parent_id` INT UNSIGNED NULL,
    `position` INT NOT NULL DEFAULT 0,
    `label_override` VARCHAR(255) NULL,
    `link_type` VARCHAR(32) NOT NULL,
    -- Soft reference to pages/posts/productions depending on link_type; intentionally
    -- has no FK constraint since it can point at three different tables.
    `target_id` INT UNSIGNED NULL,
    `custom_url` VARCHAR(2048) NULL,
    `created_at` DATETIME NOT NULL,
    `modified_at` DATETIME NOT NULL,
    CONSTRAINT `FK_MENU_ITEMS_MENU` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`) ON DELETE CASCADE,
    CONSTRAINT `FK_MENU_ITEMS_PARENT` FOREIGN KEY (`parent_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
