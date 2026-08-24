<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Enums\ContentStatus;
use TheatreCMS\Models\Page;
use TheatreCMS\Models\Person;
use TheatreCMS\Models\Post;
use TheatreCMS\Models\Production;
use TheatreCMS\Models\Season;
use TheatreCMS\Models\Work;
use TheatreCMS\Text\EditorJsHtmlConverter;
use TheatreCMS\Theme\ContentResolver;
use TheatreCMS\Twig\ContentExtension;
use Twig\Markup;

class ContentResolverTest extends TestCase
{
    private ContentResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new ContentResolver(new EditorJsHtmlConverter());
    }

    private function editorJsPayload(string $text): string
    {
        return json_encode([
            'blocks' => [
                [
                    'type' => 'paragraph',
                    'data' => ['text' => $text],
                ],
            ],
        ]);
    }

    public function testResolvesPageContentThroughEditorJsConverter(): void
    {
        $page = new Page('A Page', ContentStatus::PUBLISHED, $this->editorJsPayload('Page body'));

        $this->assertSame('<p>Page body</p>', $this->resolver->resolve($page));
    }

    public function testResolvesPostContentThroughEditorJsConverter(): void
    {
        $post = new Post('A Post', ContentStatus::PUBLISHED, $this->editorJsPayload('Post body'));

        $this->assertSame('<p>Post body</p>', $this->resolver->resolve($post));
    }

    public function testResolvesProductionDescriptionThroughEditorJsConverter(): void
    {
        $season = new Season('2025-2026', '2025-2026 Season');
        $production = new Production('Test Production', $season);
        $production->setDescription($this->editorJsPayload('Production body'));

        $this->assertSame('<p>Production body</p>', $this->resolver->resolve($production));
    }

    public function testResolvesWorkDescriptionThroughEditorJsConverter(): void
    {
        $work = new Work();
        $work->setDescription($this->editorJsPayload('Work body'));

        $this->assertSame('<p>Work body</p>', $this->resolver->resolve($work));
    }

    public function testResolvesSeasonOverviewThroughEditorJsConverter(): void
    {
        $season = new Season('2025-2026', '2025-2026 Season');
        $season->setOverview($this->editorJsPayload('Season body'));

        $this->assertSame('<p>Season body</p>', $this->resolver->resolve($season));
    }

    public function testResolvesPersonBiographyWithoutConversion(): void
    {
        $person = new Person();
        $person->setBiography('<p>Already sanitized HTML</p>');

        $this->assertSame('<p>Already sanitized HTML</p>', $this->resolver->resolve($person));
    }

    public function testThrowsForUnmappedEntity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->resolver->resolve(new \stdClass());
    }

    public function testExtensionReturnsMarkup(): void
    {
        $extension = new ContentExtension($this->resolver);
        $page = new Page('A Page', ContentStatus::PUBLISHED, $this->editorJsPayload('Page body'));

        $result = $extension->theContent($page);

        $this->assertInstanceOf(Markup::class, $result);
        $this->assertSame('<p>Page body</p>', (string)$result);
    }
}
