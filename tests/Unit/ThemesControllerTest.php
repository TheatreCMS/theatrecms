<?php

namespace TheatreCMS\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Views\Twig;
use TheatreCMS\Controllers\ThemesController;
use TheatreCMS\Settings\SiteSettings;
use TheatreCMS\Theme\ThemeManager;

#[AllowMockObjectsWithoutExpectations]
class ThemesControllerTest extends TestCase
{
    private ThemeManager $themeManager;
    private SiteSettings|MockObject $siteSettings;
    private Twig|MockObject $twig;
    private ThemesController $controller;

    protected function setUp(): void
    {
        $this->themeManager = $this->createStub(ThemeManager::class);
        $this->siteSettings = $this->createMock(SiteSettings::class);
        $this->twig         = $this->createMock(Twig::class);

        $this->themeManager->method('getActiveTheme')->willReturn('default');
        $this->themeManager->method('getAvailableThemes')->willReturn([
            'default' => ['slug' => 'default', 'name' => 'Default'],
            'dso'     => ['slug' => 'dso', 'name' => 'DSO'],
        ]);

        $this->controller = new ThemesController($this->themeManager, $this->siteSettings, $this->twig);
    }

    private function postRequest(array $body): Request
    {
        $request = $this->createStub(Request::class);
        $request->method('getParsedBody')->willReturn($body);

        return $request;
    }

    public function testIndexRendersThemesWithActiveThemeAndActivatedNotice(): void
    {
        $request = $this->createStub(Request::class);
        $request->method('getQueryParams')->willReturn(['activated' => 'dso']);

        $this->twig->expects($this->once())
            ->method('render')
            ->with(
                $this->isInstanceOf(Response::class),
                'admin/themes/index.html.twig',
                $this->callback(fn($ctx) => count($ctx['themes']) === 2
                    && $ctx['active_theme'] === 'default'
                    && $ctx['activated']['slug'] === 'dso')
            )
            ->willReturn($this->createStub(Response::class));

        $this->controller->index($request, (new ResponseFactory())->createResponse());
    }

    public function testActivateSavesInstalledThemeAndRedirects(): void
    {
        $this->siteSettings->expects($this->once())->method('saveTheme')->with('dso');

        $result = $this->controller->activate($this->postRequest(['theme' => 'dso']), (new ResponseFactory())->createResponse());

        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame('/admin/themes?activated=dso', $result->getHeaderLine('Location'));
    }

    public function testActivateRejectsUnknownTheme(): void
    {
        $this->siteSettings->expects($this->never())->method('saveTheme');
        $this->twig->expects($this->once())
            ->method('render')
            ->willReturnCallback(fn(Response $response) => $response);

        $result = $this->controller->activate($this->postRequest(['theme' => '../etc']), (new ResponseFactory())->createResponse());

        $this->assertSame(400, $result->getStatusCode());
    }
}
