<?php

use TheatreCMS\Taxonomy\TaxonomyRegistry;

/**
 * Declares a taxonomy (a named set of terms, e.g. 'genre') and the content types it
 * can be attached to (e.g. ['work']), so admins can manage its terms and assign them.
 *
 * @param string $name machine name: lowercase letters and underscores (see `TheatreCMS\Routing\NamingRules`)
 * @param string[] $contentTypes singular content-type keys, e.g. 'work', 'post'; each must already
 *        be registered
 * @param array{
 *     label?: string,
 *     singularLabel?: string,
 *     multiple?: bool,
 *     capability?: string,
 *     urlPrefix?: string,
 *     hasArchive?: bool
 * } $args
 *        `capability` gates managing the taxonomy's terms; defaults to manage_options, so match it to
 *        the content type's own capability to show the terms page wherever that content type is shown.
 *        `urlPrefix` is the path segment term archives are served under (`/{urlPrefix}/{term-slug}`);
 *        defaults to the name with underscores as hyphens. `hasArchive: false` turns the archives off.
 */
function register_taxonomy(string $name, array $contentTypes, array $args = []): void
{
    TaxonomyRegistry::getInstance()->register($name, $contentTypes, $args);
}
