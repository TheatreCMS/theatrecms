<?php

namespace TheatreCMS\Twig;

use TheatreCMS\Auth\AuthorizationService;
use TheatreCMS\Models\Term;
use TheatreCMS\Repositories\TermRepository;
use TheatreCMS\Taxonomy\TaxonomyDefinition;
use TheatreCMS\Taxonomy\TaxonomyRegistry;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Admin-side taxonomy helpers:
 * - `taxonomies_for(content_type)`: every taxonomy that can be assigned to the content type (edit forms).
 * - `admin_taxonomies(content_type)`: those whose terms the current user may manage (navigation).
 * - `taxonomy_terms(taxonomy)`: all terms of a taxonomy, alphabetically (term pickers).
 */
class TaxonomyExtension extends AbstractExtension
{
    public function __construct(
        private readonly TaxonomyRegistry $taxonomies,
        private readonly AuthorizationService $authorization,
        private readonly TermRepository $terms,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('taxonomies_for', [$this, 'taxonomiesFor']),
            new TwigFunction('admin_taxonomies', [$this, 'adminTaxonomies']),
            new TwigFunction('taxonomy_terms', [$this, 'taxonomyTerms']),
        ];
    }

    /**
     * @return array<string, TaxonomyDefinition>
     */
    public function taxonomiesFor(string $contentType): array
    {
        return $this->taxonomies->forContentType($contentType);
    }

    /**
     * @return array<string, TaxonomyDefinition>
     */
    public function adminTaxonomies(string $contentType): array
    {
        return array_filter(
            $this->taxonomies->forContentType($contentType),
            fn(TaxonomyDefinition $taxonomy): bool => $this->authorization->can($taxonomy->capability)
        );
    }

    /**
     * @return Term[]
     */
    public function taxonomyTerms(string $taxonomy): array
    {
        return $this->terms->fetchByTaxonomy($taxonomy);
    }
}
