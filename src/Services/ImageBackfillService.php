<?php

namespace TheatreCMS\Services;

use Doctrine\DBAL\Connection;
use TheatreCMS\Models\Media;
use TheatreCMS\Repositories\MediaRepository;

/**
 * One-time backfill for the media library: registers pre-existing files under
 * www/uploads/ as Media rows, then repoints the legacy `featured_image_url`
 * string columns on productions/posts/seasons at the matching Media row's id.
 *
 * Reads/writes the legacy `featured_image_url` columns via raw DBAL rather than
 * Doctrine entities, because by the time this runs against a deployed app the
 * PHP entities are already mapped to `featured_image_id` only — the column
 * still exists physically until the follow-up migration drops it, but Doctrine
 * no longer knows about it. See migrations/20260903_drop_featured_image_url_columns.sql.
 *
 * Safe to run more than once: scanUploads() skips URLs already present in the
 * `media` table, and repointColumn() only touches rows that still have a
 * `featured_image_url` but no `featured_image_id`.
 */
class ImageBackfillService
{
    private const BACKFILLABLE_TABLES = ['productions', 'posts', 'seasons'];

    public function __construct(
        private readonly Connection $connection,
        private readonly MediaRepository $mediaRepository,
        private readonly string $uploadsDir,
        private readonly string $uploadsUrlPrefix = '/uploads/'
    ) {
    }

    /**
     * @return int number of Media rows created
     */
    public function scanUploads(bool $dryRun = false): int
    {
        $files = glob(rtrim($this->uploadsDir, '/\\') . '/*') ?: [];
        $created = 0;

        foreach ($files as $path) {
            if (!is_file($path)) {
                continue;
            }

            $filename = basename($path);
            $url = $this->uploadsUrlPrefix . $filename;

            if ($this->mediaRepository->findByUrl($url) !== null) {
                continue;
            }

            if ($dryRun) {
                $created++;
                continue;
            }

            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($path) ?: null;
            $extension = pathinfo($filename, PATHINFO_EXTENSION);

            try {
                $this->mediaRepository->create([
                    'url'      => $url,
                    'filename' => $filename,
                    'mimeType' => $mimeType,
                    'sizeBytes' => filesize($path) ?: null,
                    'mediaType' => MediaTypeClassifier::classify($mimeType, $extension),
                ]);
                $created++;
            } catch (\InvalidArgumentException) {
                // A concurrent run (or the unique URL constraint) beat us to it; skip.
                continue;
            }
        }

        return $created;
    }

    /**
     * @return array<string, int> repointed row count per table
     */
    public function repointAll(bool $dryRun = false): array
    {
        $counts = [];
        foreach (self::BACKFILLABLE_TABLES as $table) {
            $counts[$table] = $this->repointColumn($table, $dryRun);
        }

        return $counts;
    }

    private function repointColumn(string $table, bool $dryRun): int
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, featured_image_url FROM {$table} WHERE featured_image_url IS NOT NULL AND featured_image_id IS NULL"
        );

        $repointed = 0;

        foreach ($rows as $row) {
            $media = $this->mediaRepository->findByUrl((string) $row['featured_image_url']);
            if ($media === null) {
                continue;
            }

            if (!$dryRun) {
                $this->connection->update($table, ['featured_image_id' => $media->getId()], ['id' => $row['id']]);
            }

            $repointed++;
        }

        return $repointed;
    }
}
