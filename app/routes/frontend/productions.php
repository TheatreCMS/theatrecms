<?php

use TheatreCMS\Middleware\RequireTwigMiddleware;
use TheatreCMS\Repositories\ProductionRepository;
use TheatreCMS\Theme\ContentTypeRegistry;
use TheatreCMS\Theme\TemplateResolver;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * @var TemplateResolver $resolver
 * @var ContentTypeRegistry $contentTypes
 */

if (isset($app)) {
    $app->get('/' . $contentTypes->prefix('productions'), function (Request $request, Response $response) use ($app, $resolver, $contentTypes) {
        $container = $app->getContainer();

        /** @var ProductionRepository $repository */
        $repository = $container->get(ProductionRepository::class);
        $productions = $repository->fetchAll();
        $productions = apply_filters('theatrecms/productions', $productions, $request);

        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        $title = $contentTypes->label('productions');

        return $resolver->renderList($twig, $response, 'productions', $title, ['productions' => $productions]);
    })->add(new RequireTwigMiddleware($app->getContainer()));
}
