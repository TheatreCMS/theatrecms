ALTER TABLE `production_works`
    ADD COLUMN `position` INT NOT NULL DEFAULT 0 AFTER `work_id`;

-- Backfill a stable per-production display order for rows that predate
-- explicit ordering, using work_id as a deterministic tiebreaker.
UPDATE `production_works` pw
JOIN (
    SELECT production_id, work_id,
           ROW_NUMBER() OVER (PARTITION BY production_id ORDER BY work_id) AS rn
    FROM `production_works`
) ranked ON ranked.production_id = pw.production_id AND ranked.work_id = pw.work_id
SET pw.`position` = ranked.rn;
