<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit\Theme;

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use TheatreCMS\ContentType\QueriedObject;
use TheatreCMS\Models\Season;
use TheatreCMS\Theme\SeoMeta;
use TheatreCMS\Theme\SeoTagBuilder;
use TheatreCMS\Theme\TemplateResolver;
use TheatreCMS\Theme\TitleResolver;
use Twig\Loader\ArrayLoader;

/**
 * renderSingle()/renderArchive() resolve templates from `$templateDir` when given, while
 * QueriedObject still sees the content-type key.
 */
class TemplateResolverTemplateDirTest extends TestCase
{
    private Twig $twig;
    private QueriedObject $queriedObject;
    private TemplateResolver $resolver;

    protected function setUp(): void
    {
        $this->twig = new Twig(new ArrayLoader([
            'productions/archive.html.twig' => 'plural archive',
            'productions/single.html.twig' => 'plural single',
            'production/archive.html.twig' => 'key archive',
            'production/single.html.twig' => 'key single',
            'index.html.twig' => 'index',
        ]));
        $this->queriedObject = new QueriedObject();

        $seo = new SeoMeta('', '', '', '', 'website', 'summary');
        $seoTagBuilder = $this->createStub(SeoTagBuilder::class);
        $seoTagBuilder->method('forArchive')->willReturn($seo);
        $seoTagBuilder->method('forEntity')->willReturn($seo);

        $this->resolver = new TemplateResolver(new TitleResolver(), $this->queriedObject, $seoTagBuilder);
    }

    public function testRenderArchiveUsesTheTemplateDir(): void
    {
        $response = $this->resolver->renderArchive(
            $this->twig,
            new Response(),
            'production',
            'Productions',
            [],
            templateDir: 'productions'
        );

        $this->assertSame('plural archive', (string) $response->getBody());
        $this->assertTrue($this->queriedObject->isArchive('production'));
    }

    public function testRenderArchiveDefaultsTheTemplateDirToTheType(): void
    {
        $response = $this->resolver->renderArchive($this->twig, new Response(), 'production', 'Productions', []);

        $this->assertSame('key archive', (string) $response->getBody());
    }

    public function testRenderSingleUsesTheTemplateDir(): void
    {
        $response = $this->resolver->renderSingle(
            $this->twig,
            new Response(),
            'production',
            new Season('2026', '2026 Season'),
            templateDir: 'productions'
        );

        $this->assertSame('plural single', (string) $response->getBody());
        $this->assertTrue($this->queriedObject->isSingular('production'));
    }
}
