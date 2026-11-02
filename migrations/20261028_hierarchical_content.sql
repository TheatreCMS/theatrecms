-- Hierarchical content (Page, Entry via src/Traits/HasParent.php) and season-scoped production
-- slugs (src/Models/Production.php).
--
-- Pages and entries get a self-referencing parent_id; deleting a parent sets its children's to
-- NULL as a backstop, though the repositories move children up to the deleted item's parent first.
--
-- Slugs only need to be unique among siblings: /about/team and /contact/team can both exist, and
-- a production's slug only within its season (/seasons/2005/the-glass-menagerie and
-- /seasons/2010/the-glass-menagerie). parent_scope mirrors parent_id but maps NULL to zero, letting
-- the database enforce unique top-level page slugs as well as child slugs. Entry slugs stay unique
-- per content type (uniq_entries_type_slug), so entries need no new key.

ALTER TABLE pages DROP INDEX UNIQ_2074E575989D9B62;
ALTER TABLE pages ADD parent_id INT DEFAULT NULL;
ALTER TABLE pages ADD parent_scope INT NOT NULL DEFAULT 0;
ALTER TABLE pages ADD CONSTRAINT FK_2074E575727ACA70 FOREIGN KEY (parent_id) REFERENCES pages (id) ON DELETE SET NULL;
CREATE UNIQUE INDEX uniq_pages_parent_scope_slug ON pages (parent_scope, slug);
CREATE INDEX IDX_2074E575727ACA70 ON pages (parent_id);

ALTER TABLE productions DROP INDEX UNIQ_BBD7C0C2989D9B62;
CREATE UNIQUE INDEX uniq_productions_season_slug ON productions (season_id, slug);

ALTER TABLE entries ADD parent_id INT DEFAULT NULL;
ALTER TABLE entries ADD CONSTRAINT FK_2DF8B3C5727ACA70 FOREIGN KEY (parent_id) REFERENCES entries (id) ON DELETE SET NULL;
CREATE INDEX IDX_2DF8B3C5727ACA70 ON entries (parent_id);
