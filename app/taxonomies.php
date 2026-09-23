<?php

use TheatreCMS\Taxonomy\TaxonomyRegistry;

/**
 * Declares a taxonomy (a named set of terms, e.g. 'genre') and the content types it
 * can be attached to (e.g. ['work']), so admins can manage its terms and assign them.
 *
 * @param string $name machine name, 1-32 lowercase letters/digits/underscores
 * @param string[] $contentTypes singular content-type keys, e.g. 'work', 'post'
 * @param array{label?: string, singular_label?: string, multiple?: bool, capability?: string} $args
 *        `capability` gates managing the taxonomy's terms; defaults to manage_options, so match it to
 *        the content type's own capability to show the terms page wherever that content type is shown.
 */
function register_taxonomy(string $name, array $contentTypes, array $args = []): void
{
    TaxonomyRegistry::getInstance()->register($name, $contentTypes, $args);
}
