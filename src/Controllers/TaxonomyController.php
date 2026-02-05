<?php

namespace Clubdeuce\TheatreCMS\Controllers;

use Clubdeuce\TheatreCMS\Models\Taxonomy;

class TaxonomyController
{
    /**
     * @var Taxonomy[]
     */
    protected array $taxonomies = [];

    public function register_taxonomy(string $slug, $args = [])
    {
        $this->taxonomies[$slug] = (new Taxonomy())
            ->setDescription($args['description'])
            ->setLabel($args['label'])
            ->setSlug($slug);
    }

    /**
     * @return Taxonomy[]
     */
    public function get_taxonomies(): array
    {
        return $this->taxonomies;
    }

    public function get_taxonomy(string $slug): Taxonomy
    {
        if (isset($this->taxonomies[$slug]))
            return $this->taxonomies[$slug];

        trigger_error('Taxonomy not found', E_USER_ERROR);
    }

    public function taxonomy_exists(string $slug): bool
    {
        return isset($this->taxonomies[$slug]);
    }
}