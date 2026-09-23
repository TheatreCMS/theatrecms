<?php

namespace TheatreCMS\Traits;

use InvalidArgumentException;
use TheatreCMS\Repositories\TermRelationshipRepository;

/**
 * Shared term-assignment write path for controllers whose edit forms include
 * `admin/partials/_taxonomy_fields.html.twig`, which posts `terms[<taxonomy>][] = <term id>`.
 *
 * The partial always sends one empty value per taxonomy, so an unticked taxonomy clears that
 * item's terms, while a form without the partial (e.g. a quick-create modal) sends no `terms`
 * key and leaves existing assignments untouched.
 */
trait AssignsTaxonomyTerms
{
    abstract protected function termRelationships(): TermRelationshipRepository;

    /**
     * @param array<string, mixed> $data the parsed request body
     * @throws InvalidArgumentException for a taxonomy that doesn't apply to the content type, a term
     *         of another taxonomy, or several terms for a single-term taxonomy
     */
    protected function applyTerms(string $contentType, int $contentId, array $data): void
    {
        if (!isset($data['terms']) || !is_array($data['terms'])) {
            return;
        }

        foreach ($data['terms'] as $taxonomy => $termIds) {
            $termIds = array_filter((array) $termIds, static fn($id): bool => $id !== '' && $id !== null);
            $this->termRelationships()->setTerms($contentType, $contentId, (string) $taxonomy, $termIds);
        }
    }
}
