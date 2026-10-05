-- Content timestamps (THE-136): created_at and modified_at on the content tables that lacked them,
-- matching posts, pages, terms, menus, menu_items and content_meta. The models keep them current
-- through the HasCreatedTimestamp / HasModifiedTimestamp lifecycle callbacks.
--
-- Existing rows have no record of when they were created, so they get the migration time, except
-- media, which has uploaded_at. Columns are added nullable, backfilled, then made NOT NULL.

ALTER TABLE events ADD created_at DATETIME DEFAULT NULL, ADD modified_at DATETIME DEFAULT NULL;
ALTER TABLE media ADD created_at DATETIME DEFAULT NULL, ADD modified_at DATETIME DEFAULT NULL;
ALTER TABLE people ADD created_at DATETIME DEFAULT NULL, ADD modified_at DATETIME DEFAULT NULL;
ALTER TABLE productions ADD created_at DATETIME DEFAULT NULL, ADD modified_at DATETIME DEFAULT NULL;
ALTER TABLE seasons ADD created_at DATETIME DEFAULT NULL, ADD modified_at DATETIME DEFAULT NULL;
ALTER TABLE sponsors ADD created_at DATETIME DEFAULT NULL, ADD modified_at DATETIME DEFAULT NULL;
ALTER TABLE venues ADD created_at DATETIME DEFAULT NULL, ADD modified_at DATETIME DEFAULT NULL;
ALTER TABLE works ADD created_at DATETIME DEFAULT NULL, ADD modified_at DATETIME DEFAULT NULL;

UPDATE media SET created_at = uploaded_at, modified_at = uploaded_at;
UPDATE events SET created_at = NOW(), modified_at = NOW();
UPDATE people SET created_at = NOW(), modified_at = NOW();
UPDATE productions SET created_at = NOW(), modified_at = NOW();
UPDATE seasons SET created_at = NOW(), modified_at = NOW();
UPDATE sponsors SET created_at = NOW(), modified_at = NOW();
UPDATE venues SET created_at = NOW(), modified_at = NOW();
UPDATE works SET created_at = NOW(), modified_at = NOW();

ALTER TABLE events MODIFY created_at DATETIME NOT NULL, MODIFY modified_at DATETIME NOT NULL;
ALTER TABLE media MODIFY created_at DATETIME NOT NULL, MODIFY modified_at DATETIME NOT NULL;
ALTER TABLE people MODIFY created_at DATETIME NOT NULL, MODIFY modified_at DATETIME NOT NULL;
ALTER TABLE productions MODIFY created_at DATETIME NOT NULL, MODIFY modified_at DATETIME NOT NULL;
ALTER TABLE seasons MODIFY created_at DATETIME NOT NULL, MODIFY modified_at DATETIME NOT NULL;
ALTER TABLE sponsors MODIFY created_at DATETIME NOT NULL, MODIFY modified_at DATETIME NOT NULL;
ALTER TABLE venues MODIFY created_at DATETIME NOT NULL, MODIFY modified_at DATETIME NOT NULL;
ALTER TABLE works MODIFY created_at DATETIME NOT NULL, MODIFY modified_at DATETIME NOT NULL;
