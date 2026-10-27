<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit;

use LogicException;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use TheatreCMS\ContentType\ContentTypeDefinition;
use TheatreCMS\Controllers\BaseController;
use TheatreCMS\Repositories\PaginatedRepositoryInterface;
use TheatreCMS\Theme\TemplateResolver;
use TheatreCMS\Traits\RendersPublicContent;

/**
 * @coversDefaultClass \TheatreCMS\Traits\RendersPublicContent
 */
class RendersPublicContentTest extends TestCase
{
    private ContentTypeDefinition $definition;

    protected function setUp(): void
    {
        $this->definition = new ContentTypeDefinition('press_release', 'Press Releases', 'Press Release', 'press-releases');
    }

    public function testArchiveRendersPublicItemsUnderTheTypesTemplateDirectory(): void
    {
        $items = [(object) ['slug' => 'one']];
        $repository = $this->createStub(PublicRepository::class);
        $repository->method('fetchPublic')->willReturn($items);

        $resolver = $this->createMock(TemplateResolver::class);
        $resolver->expects($this->once())
            ->method('renderList')
            ->with(
                $this->anything(),
                $this->anything(),
                'press_release',
                'Press Releases',
                $items,
                ['press_releases' => $items],
                'press-releases'
            )
            ->willReturn(new Response());

        $this->controller($repository, $resolver)->archive($this->request(), new Response());
    }

    public function testSingleRendersThePublicItemWithThatSlug(): void
    {
        $item = (object) ['slug' => 'one'];
        $repository = $this->createMock(PublicRepository::class);
        $repository->expects($this->once())->method('fetchPublicBySlug')->with('one')->willReturn($item);

        $resolver = $this->createMock(TemplateResolver::class);
        $resolver->expects($this->once())
            ->method('renderSingle')
            ->with($this->anything(), $this->anything(), 'press_release', $item, ['press_release' => $item], 'press-releases')
            ->willReturn(new Response());

        $this->controller($repository, $resolver)->single($this->request(), new Response(), ['slug' => 'one']);
    }

    public function testSingleIs404WhenNoPublicItemHasTheSlug(): void
    {
        $repository = $this->createStub(PublicRepository::class);
        $repository->method('fetchPublicBySlug')->willReturn(null);

        $resolver = $this->createMock(TemplateResolver::class);
        $resolver->expects($this->never())->method('renderSingle');

        $response = $this->controller($repository, $resolver)->single($this->request(), new Response(), ['slug' => 'draft']);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testThrowsWithoutTheContentTypeAttribute(): void
    {
        $controller = $this->controller(
            $this->createStub(PublicRepository::class),
            $this->createStub(TemplateResolver::class)
        );

        $this->expectException(LogicException::class);
        $controller->archive((new ServerRequestFactory())->createServerRequest('GET', '/press-releases'), new Response());
    }

    private function request(): \Psr\Http\Message\ServerRequestInterface
    {
        return (new ServerRequestFactory())
            ->createServerRequest('GET', '/press-releases')
            ->withAttribute(ContentTypeDefinition::class, $this->definition);
    }

    private function controller(PaginatedRepositoryInterface $repository, TemplateResolver $resolver): BaseController
    {
        return new class ($repository, $this->createStub(Twig::class), $resolver) extends BaseController {
            use RendersPublicContent;

            public function __construct(
                PaginatedRepositoryInterface $repository,
                Twig $twig,
                private readonly TemplateResolver $resolver
            ) {
                parent::__construct($repository, $twig);
            }

            protected function templateResolver(): TemplateResolver
            {
                return $this->resolver;
            }
        };
    }
}

/**
 * The repository surface RendersPublicContent uses, for mocking.
 */
interface PublicRepository extends PaginatedRepositoryInterface
{
    /** @return array<int, object> */
    public function fetchPublic(): array;

    public function fetchPublicBySlug(string $slug): ?object;
}
