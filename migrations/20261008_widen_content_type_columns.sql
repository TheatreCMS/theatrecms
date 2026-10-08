-- Drop the 32-character cap on content-type and taxonomy keys (THE-106). The cap had no recorded
-- reason: content_meta introduced it and later tables copied it. Names are validated for
-- characters only (TheatreCMS\Routing\NamingRules), so the columns must hold any registered name.
--
-- The columns stay VARCHAR because they are in indexes (a TEXT column can only be prefix-indexed);
-- 255 is Doctrine's default length. Every affected index stays under InnoDB's 3072-byte limit in
-- utf8mb4, the largest being uniq_content_meta_key at about 1788 bytes.

ALTER TABLE content_meta MODIFY content_type VARCHAR(255) NOT NULL;
ALTER TABLE term_relationships MODIFY content_type VARCHAR(255) NOT NULL;
ALTER TABLE external_references MODIFY content_type VARCHAR(255) NOT NULL;
ALTER TABLE terms MODIFY taxonomy VARCHAR(255) NOT NULL;
