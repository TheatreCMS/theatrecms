ALTER TABLE `events`
    ADD COLUMN `ends_at` datetime DEFAULT NULL AFTER `starts_at`;
