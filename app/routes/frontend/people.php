<?php

use TheatreCMS\Middleware\RequireTwigMiddleware;
use TheatreCMS\Repositories\PersonRepository;
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
    $app->group('/' . $contentTypes->prefix('people'), function ($group) use ($resolver) {
        $container = $group->getContainer();

        $group->get('/{slug}', function (
            Request $request,
            Response $response,
            array $args
        ) use (
            $container,
            $resolver
        ) {
            /** @var PersonRepository $repository */
            $repository = $container->get(PersonRepository::class);
            $person = $repository->fetchBySlug($args['slug']);

            if (!$person) {
                $response->getBody()->write('Person not found');

                return $response->withStatus(404);
            }

            $person = apply_filters('theatrecms/person', $person, $request, $args);

            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            return $resolver->renderSingle($twig, $response, 'people', $person, ['person' => $person]);
        });
    })->add(new RequireTwigMiddleware($app->getContainer()));
}
