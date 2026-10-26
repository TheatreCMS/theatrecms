<?php

namespace TheatreCMS\Controllers;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use TheatreCMS\ContentType\ContentTypeDefinition;
use TheatreCMS\Repositories\ContentMetaRepository;
use TheatreCMS\Repositories\EntryRepository;
use TheatreCMS\Traits\HandlesContentImages;

class EntryController extends BaseController
{
    use HandlesContentImages;

    public function __construct(
        EntryRepository $repository,
        EntityManagerInterface $em,
        Twig $twig,
        private readonly ContentTypeDefinition $definition,
        private readonly ContentMetaRepository $contentMeta
    ) {
        parent::__construct($repository->forType($definition->name), $twig, $em);
    }

    public function contentType(): string
    {
        return $this->definition->name;
    }

    public function index(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render(
            $response,
            'admin/entries/index.html.twig',
            $this->buildPaginatedViewData($request, $this->repository, 'entries', '/admin/entries')
        );
    }


    public function create()
    {
    }

    public function edit()
    {
    }

    public function store()
    {
    }

    public function update()
    {
    }

    public function destroy()
    {
    }
}
