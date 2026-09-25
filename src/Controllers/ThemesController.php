<?php

namespace TheatreCMS\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use TheatreCMS\Settings\SiteSettings;
use TheatreCMS\Theme\ThemeManager;

class ThemesController
{
    public function __construct(
        private readonly ThemeManager $themeManager,
        private readonly SiteSettings $siteSettings,
        private readonly Twig $twig,
    ) {
    }

    public function index(Request $request, Response $response, array $args = []): Response
    {
        $themes = $this->themeManager->getAvailableThemes();
        $activated = $request->getQueryParams()['activated'] ?? null;

        return $this->twig->render($response, 'admin/themes/index.html.twig', [
            'themes'       => $themes,
            'active_theme' => $this->themeManager->getActiveTheme(),
            'activated'    => is_string($activated) && isset($themes[$activated]) ? $themes[$activated] : null,
        ]);
    }

    public function activate(Request $request, Response $response, array $args = []): Response
    {
        $data = $request->getParsedBody();
        $slug = is_array($data) ? (string) ($data['theme'] ?? '') : '';

        if (!isset($this->themeManager->getAvailableThemes()[$slug])) {
            return $this->twig->render($response->withStatus(400), 'admin/partials/_alert.html.twig', [
                'type'    => 'error',
                'message' => 'That theme is not installed.',
            ]);
        }

        $this->siteSettings->saveTheme($slug);

        // Full reload rather than an HTMX partial: switching themes changes the
        // whole list's "active" state, and the change only takes effect on the next request.
        return $response
            ->withHeader('Location', '/admin/themes?activated=' . rawurlencode($slug))
            ->withStatus(302);
    }
}
