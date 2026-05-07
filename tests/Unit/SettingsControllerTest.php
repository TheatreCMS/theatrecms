<?php

namespace TheatreCMS\Tests\Unit;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use TheatreCMS\Controllers\SettingsController;
use TheatreCMS\Settings\SiteSettings;

class SettingsControllerTest extends TestCase
{
    private SiteSettings|MockObject $siteSettings;
    private Twig|MockObject $twig;

    protected function setUp(): void
    {
        $this->siteSettings = $this->createMock(SiteSettings::class);
        $this->twig         = $this->createMock(Twig::class);
    }

    public function testIndexRendersTemplate(): void
    {
        $controller = new SettingsController($this->siteSettings, $this->twig);

        $this->siteSettings->method('all')->willReturn(['name' => 'TestCMS']);

        $request  = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);

        $this->twig->expects($this->once())
            ->method('render')
            ->with(
                $this->isInstanceOf(Response::class),
                'admin/settings/index.html.twig',
                $this->callback(fn($ctx) => isset($ctx['settings']))
            )
            ->willReturn($this->createMock(Response::class));

        $result = $controller->index($request, $response);

        $this->assertInstanceOf(Response::class, $result);
    }

    public function testUpdateReturns400WhenNoBody(): void
    {
        $controller = new SettingsController($this->siteSettings, $this->twig);

        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn([]);
        $request->method('getHeaderLine')->willReturn('');

        $response = $this->createMock(Response::class);
        $response->method('withStatus')->with(400)->willReturn($response);

        $this->siteSettings->expects($this->never())->method('save');

        $result = $controller->update($request, $response);

        $this->assertInstanceOf(Response::class, $result);
    }

    public function testUpdateSavesSettingsAndRedirects(): void
    {
        $controller = new SettingsController($this->siteSettings, $this->twig);

        $data = [
            'organization_name' => 'Acme Theatre',
            'name'              => 'Acme CMS',
            'logo_url'          => '/img/logo.png',
            'contact_email'     => 'info@acme.org',
        ];

        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($data);
        $request->method('getHeaderLine')->willReturn('');

        $response = $this->createMock(Response::class);
        $redirectResponse = $this->createMock(Response::class);
        $response->method('withHeader')->with('Location', '/admin/settings')->willReturn($redirectResponse);
        $redirectResponse->method('withStatus')->with(302)->willReturn($redirectResponse);

        $this->siteSettings->expects($this->once())->method('save')->with($data);

        $result = $controller->update($request, $response);

        $this->assertInstanceOf(Response::class, $result);
    }

    public function testUpdateReturnsHtmxAlertOnSuccess(): void
    {
        $controller = new SettingsController($this->siteSettings, $this->twig);

        $data = ['organization_name' => 'Acme Theatre'];

        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($data);
        $request->method('getHeaderLine')->with('HX-Request')->willReturn('true');

        $response = $this->createMock(Response::class);

        $this->twig->expects($this->once())
            ->method('render')
            ->with(
                $this->isInstanceOf(Response::class),
                'admin/partials/_alert.html.twig',
                $this->callback(fn($ctx) => $ctx['type'] === 'success')
            )
            ->willReturn($this->createMock(Response::class));

        $this->siteSettings->expects($this->once())->method('save');

        $result = $controller->update($request, $response);

        $this->assertInstanceOf(Response::class, $result);
    }
}
