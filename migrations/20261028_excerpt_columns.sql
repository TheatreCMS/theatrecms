-- Excerpts come from the HasExcerpt trait (src/Traits/HasExcerpt.php), mapped as TEXT on every
-- model that uses it. Productions had VARCHAR(255) with no recorded reason for the limit, so
-- widen it to match entries.excerpt; posts gain the column.

ALTER TABLE productions MODIFY excerpt LONGTEXT DEFAULT NULL;
ALTER TABLE posts ADD excerpt LONGTEXT DEFAULT NULL;
