<?php

namespace TheatreCMS\Controllers;

use TheatreCMS\Settings\SiteSettings;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class SettingsController
{
    public function __construct(
        private readonly SiteSettings $siteSettings,
        private readonly Twig $twig,
    ) {
    }

    public function index(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render($response, 'admin/settings/index.html.twig', [
            'settings' => $this->siteSettings->all(),
        ]);
    }

    public function update(Request $request, Response $response, array $args = []): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Unable to save settings. Please check your input.',
                ]);
            }
            return $response->withStatus(400);
        }

        $this->siteSettings->save($data);

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                'type'    => 'success',
                'message' => 'Settings saved successfully.',
            ]);
        }

        return $response->withHeader('Location', '/admin/settings')->withStatus(302);
    }
}
