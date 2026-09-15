<?php

namespace TheatreCMS\Services;

use Doctrine\ORM\EntityManagerInterface;
use TheatreCMS\Models\Media;
use TheatreCMS\Theme\ImageSizeRegistry;

/**
 * Generates missing (or, with --force, all) registered thumbnail sizes for
 * every image already in the media library — for media uploaded before a
 * size was registered, or before this feature existed at all.
 *
 * Safe to run more than once: without --force it only generates sizes a
 * given Media row doesn't already have a MediaVariant for.
 */
class MediaVariantBackfillService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ImageVariantGenerator $generator,
        private readonly ImageSizeRegistry $imageSizeRegistry
    ) {
    }

    /**
     * @return array<string, int> generated variant count per size name
     */
    public function regenerate(bool $dryRun = false, bool $force = false, ?string $onlySize = null): array
    {
        $sizeNames = array_keys($this->imageSizeRegistry->all());
        if ($onlySize !== null) {
            $sizeNames = in_array($onlySize, $sizeNames, true) ? [$onlySize] : [];
        }

        $counts = array_fill_keys($sizeNames, 0);

        /** @var Media[] $images */
        $images = $this->em->getRepository(Media::class)->findBy(['mediaType' => Media::TYPE_IMAGE]);

        foreach ($images as $media) {
            $missing = $force ? $sizeNames : $this->missingSizes($media, $sizeNames);

            if ($missing === []) {
                continue;
            }

            foreach ($missing as $sizeName) {
                $counts[$sizeName]++;
            }

            if (!$dryRun) {
                $this->generator->generate($media, $missing);
            }
        }

        return $counts;
    }

    /**
     * @param array<int, string> $sizeNames
     * @return array<int, string>
     */
    private function missingSizes(Media $media, array $sizeNames): array
    {
        $existing = [];
        foreach ($media->getVariants() as $variant) {
            $existing[$variant->getSizeName()] = true;
        }

        return array_values(array_filter($sizeNames, static fn(string $name): bool => !isset($existing[$name])));
    }
}
