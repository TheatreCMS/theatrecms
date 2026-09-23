<?php

namespace TheatreCMS\Twig;

use TheatreCMS\Models\Term;
use TheatreCMS\Repositories\TermRelationshipRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes `get_terms(content_type, id, taxonomy)` so themes can list the taxonomy terms
 * assigned to a content item, e.g. `{% for genre in get_terms('work', work.id, 'genre') %}`.
 */
class TermsExtension extends AbstractExtension
{
    public function __construct(private readonly TermRelationshipRepository $relationships)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_terms', [$this, 'getTerms']),
        ];
    }

    /**
     * @return Term[]
     */
    public function getTerms(string $contentType, int $contentId, ?string $taxonomy = null): array
    {
        return $this->relationships->termsFor($contentType, $contentId, $taxonomy);
    }
}
