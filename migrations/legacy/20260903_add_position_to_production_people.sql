ALTER TABLE `production_people`
    ADD COLUMN `position` INT NOT NULL DEFAULT 0 AFTER `role_type`;

-- Backfill a stable per-production, per-role-type display order for rows that
-- predate explicit ordering, using person_id as a deterministic tiebreaker.
UPDATE `production_people` pp
JOIN (
    SELECT production_id, person_id, role_type,
           ROW_NUMBER() OVER (PARTITION BY production_id, role_type ORDER BY person_id) AS rn
    FROM `production_people`
) ranked ON ranked.production_id = pp.production_id
    AND ranked.person_id = pp.person_id
    AND ranked.role_type <=> pp.role_type
SET pp.`position` = ranked.rn;
