-- Adds an optional caption field to the media library, editable alongside
-- alt text from the media details modal (see templates/admin/media/_details.html.twig).
ALTER TABLE `media`
    ADD COLUMN `caption` TEXT NULL AFTER `alt_text`;
