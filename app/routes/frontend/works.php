<?php

use TheatreCMS\Middleware\RequireTwigMiddleware;
use TheatreCMS\Repositories\WorkRepository;
use TheatreCMS\Theme\TemplateResolver;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * @var TemplateResolver $resolver
 */

if (isset($app)) {
    $app->group('/works', function ($group) use ($resolver) {
        $container = $group->getContainer();

        $group->get('/{slug}', function (
            Request $request,
            Response $response,
            array $args
        ) use (
            $container,
            $resolver
        ) {
            /** @var WorkRepository $repository */
            $repository = $container->get(WorkRepository::class);
            $work = $repository->fetchBySlug($args['slug']);

            if (!$work) {
                $response->getBody()->write('Work not found');

                return $response->withStatus(404);
            }

            $work = apply_filters('theatrecms/work', $work, $request, $args);

            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            $template = $resolver->resolve(
                $twig,
                'works/single-' . $work->getSlug() . '.html.twig',
                'works/single.html.twig',
                'index.html.twig'
            );

            return $twig->render($response, $template, ['work' => $work]);
        });
    })->add(new RequireTwigMiddleware($app->getContainer()));
}
