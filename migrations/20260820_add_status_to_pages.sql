ALTER TABLE `pages`
    ADD COLUMN `status` VARCHAR(32) NOT NULL DEFAULT 'published' AFTER `title`;

-- Match posts.status exactly: no DB-level default, the app always supplies
-- a status explicitly. The DEFAULT above only exists to backfill existing
-- rows as 'published' (they predate this column and were already live).
ALTER TABLE `pages`
    ALTER COLUMN `status` DROP DEFAULT;
