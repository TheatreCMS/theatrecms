<?php

namespace TheatreCMS\Tests\Unit\Theme;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Views\Twig;
use TheatreCMS\Theme\QueriedObject;
use TheatreCMS\Theme\SeoTagBuilder;
use TheatreCMS\Theme\TemplateResolver;
use TheatreCMS\Theme\TitleResolver;
use Twig\Loader\ArrayLoader;

class TemplateResolverTermTest extends TestCase
{
    /**
     * @param string[] $available
     */
    #[DataProvider('hierarchyProvider')]
    public function testResolveTermPrefersTheMostSpecificTemplate(array $available, string $expected): void
    {
        $twig = new Twig(new ArrayLoader(array_fill_keys($available, '')));
        $resolver = new TemplateResolver(
            new TitleResolver(),
            new QueriedObject(),
            $this->createStub(SeoTagBuilder::class)
        );

        $this->assertSame($expected, $resolver->resolveTerm($twig, 'genre', 'comedy'));
    }

    /**
     * @return array<string, array{string[], string}>
     */
    public static function hierarchyProvider(): array
    {
        $all = [
            'taxonomy/genre-comedy.html.twig',
            'taxonomy/genre.html.twig',
            'taxonomy.html.twig',
            'list.html.twig',
            'index.html.twig',
        ];

        return [
            'term-specific' => [$all, 'taxonomy/genre-comedy.html.twig'],
            'taxonomy-specific' => [array_slice($all, 1), 'taxonomy/genre.html.twig'],
            'any taxonomy' => [array_slice($all, 2), 'taxonomy.html.twig'],
            'generic list' => [array_slice($all, 3), 'list.html.twig'],
            'index' => [['index.html.twig'], 'index.html.twig'],
        ];
    }
}
