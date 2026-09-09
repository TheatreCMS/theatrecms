<?php

namespace TheatreCMS\Theme;

use TheatreCMS\Models\Page;
use TheatreCMS\Models\Person;
use TheatreCMS\Models\Post;
use TheatreCMS\Models\Production;
use TheatreCMS\Models\Season;
use TheatreCMS\Models\Venue;
use TheatreCMS\Models\Work;
use TheatreCMS\Text\EditorJsHtmlConverter;
use TheatreCMS\Text\PlainText;

/**
 * Derives a fallback meta-description for any content entity, for use when
 * a more specific description isn't available. Prefers an entity's own
 * plain-text teaser field (`excerpt`/`synopsis`) over truncating its full
 * body content, mirroring how `TitleResolver`/`ContentResolver` resolve
 * their own per-entity fields via a single `match(true)`.
 *
 * Event and Sponsor have no public-facing copy to draw from, so they
 * resolve to an empty string — the caller (`SeoTagBuilder`) falls through
 * to a site-wide default in that case.
 */
class SeoDescriptionResolver
{
    private const MAX_LENGTH = 160;

    public function __construct(private readonly EditorJsHtmlConverter $converter)
    {
    }

    public function resolve(mixed $entity): string
    {
        $raw = match (true) {
            $entity instanceof Production => $entity->getExcerpt() ?: $this->fromEditorJs($entity->getDescription()),
            $entity instanceof Work => $entity->getSynopsis() ?: $this->fromEditorJs($entity->getDescription()),
            $entity instanceof Season => $this->fromEditorJs($entity->getOverview()),
            $entity instanceof Page => $this->fromEditorJs($entity->getContent()),
            $entity instanceof Post => $this->fromEditorJs($entity->getContent()),
            $entity instanceof Person => PlainText::fromHtml($entity->getBiography()),
            $entity instanceof Venue => (string) $entity->getDescription(),
            default => '',
        };

        return PlainText::truncate($raw, self::MAX_LENGTH);
    }

    private function fromEditorJs(string $payload): string
    {
        return $payload === '' ? '' : PlainText::fromHtml($this->converter->toHtml($payload));
    }
}
