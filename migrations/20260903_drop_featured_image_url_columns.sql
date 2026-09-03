-- Apply only after the application code that reads/writes `featured_image_id`
-- (instead of `featured_image_url`) has been deployed and confirmed healthy,
-- and after `./backfill-images` has run at least once against production data.
-- See documentation/DEPLOYMENT.md for the full rollout sequence.

ALTER TABLE `productions` DROP COLUMN `featured_image_url`;
ALTER TABLE `posts` DROP COLUMN `featured_image_url`;
ALTER TABLE `seasons` DROP COLUMN `featured_image_url`;
