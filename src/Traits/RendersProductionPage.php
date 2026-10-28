<?php

namespace TheatreCMS\Traits;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use TheatreCMS\ContentType\ContentTypeRegistry;
use TheatreCMS\Models\Production;
use TheatreCMS\Repositories\EventRepository;
use TheatreCMS\Theme\TemplateResolver;

/**
 * Renders a production's public page with its performances, shared by the production's own
 * single page and the season's nested `/{seasonSlug}/{productionSlug}` page so both show the
 * same thing: `production` and `performances` in the context, through the
 * `theatrecms/production` filter, under the production type's template directory.
 */
trait RendersProductionPage
{
    abstract protected function templateResolver(): TemplateResolver;

    abstract protected function eventRepository(): EventRepository;

    /**
     * @param array<string, mixed> $args the route arguments, passed on to the filter
     */
    protected function renderProductionPage(
        Request $request,
        Response $response,
        array $args,
        Production $production
    ): Response {
        $production = apply_filters('theatrecms/production', $production, $request, $args);

        return $this->templateResolver()->renderSingle(
            $this->twig,
            $response,
            'production',
            $production,
            [
                'production' => $production,
                'performances' => $this->eventRepository()->fetchByProduction($production->getId()),
            ],
            ContentTypeRegistry::getInstance()->require('production')->urlPrefix
        );
    }
}
