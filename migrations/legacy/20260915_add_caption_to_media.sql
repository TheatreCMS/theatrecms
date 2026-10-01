-- Adds an optional caption field to the media library, editable alongside
-- alt text from the media details modal. Doctrine already creates this column
-- on a fresh schema, so keep the transition migration safe to re-run.
SET @add_media_caption = IF(
    NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'media'
          AND COLUMN_NAME = 'caption'
    ),
    'ALTER TABLE `media` ADD COLUMN `caption` TEXT NULL AFTER `alt_text`',
    'DO 0'
);
PREPARE add_media_caption FROM @add_media_caption;
EXECUTE add_media_caption;
DEALLOCATE PREPARE add_media_caption;
