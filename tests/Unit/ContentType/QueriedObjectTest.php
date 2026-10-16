<?php

namespace TheatreCMS\Tests\Unit\ContentType;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Models\Term;
use TheatreCMS\ContentType\QueriedObject;

class QueriedObjectTest extends TestCase
{
    public function testDefaultStateIsNeitherSingleNorArchive(): void
    {
        $queriedObject = new QueriedObject();

        $this->assertFalse($queriedObject->isSingle());
        $this->assertFalse($queriedObject->isArchive());
        $this->assertFalse($queriedObject->isSingular('production'));
        $this->assertFalse($queriedObject->isItem('some-slug'));
        $this->assertFalse($queriedObject->isItemId(1));
    }

    public function testSetSingleMarksIsSingleAndClearsIsArchive(): void
    {
        $queriedObject = new QueriedObject();
        $queriedObject->setArchive('production');
        $queriedObject->setSingle('production', $this->makeEntity(1, 'hamlet'));

        $this->assertTrue($queriedObject->isSingle());
        $this->assertFalse($queriedObject->isArchive());
    }

    public function testIsSingularMatchesOnlyTheQueriedType(): void
    {
        $queriedObject = new QueriedObject();
        $queriedObject->setSingle('production', $this->makeEntity(1, 'hamlet'));

        $this->assertTrue($queriedObject->isSingular('production'));
        $this->assertFalse($queriedObject->isSingular('season'));
    }

    public function testIsItemMatchesOnlyTheQueriedSlug(): void
    {
        $queriedObject = new QueriedObject();
        $queriedObject->setSingle('production', $this->makeEntity(1, 'hamlet'));

        $this->assertTrue($queriedObject->isItem('hamlet'));
        $this->assertFalse($queriedObject->isItem('macbeth'));
    }

    public function testIsItemIdMatchesOnlyTheQueriedId(): void
    {
        $queriedObject = new QueriedObject();
        $queriedObject->setSingle('production', $this->makeEntity(42, 'hamlet'));

        $this->assertTrue($queriedObject->isItemId(42));
        $this->assertTrue($queriedObject->isItemId('42'));
        $this->assertFalse($queriedObject->isItemId(7));
    }

    public function testSetArchiveMarksIsArchiveAndClearsIsSingle(): void
    {
        $queriedObject = new QueriedObject();
        $queriedObject->setSingle('production', $this->makeEntity(1, 'hamlet'));
        $queriedObject->setArchive('production');

        $this->assertTrue($queriedObject->isArchive());
        $this->assertFalse($queriedObject->isSingle());
        $this->assertFalse($queriedObject->isItem('hamlet'));
        $this->assertFalse($queriedObject->isItemId(1));
    }

    public function testIsArchiveMatchesOnlyTheQueriedTypeWhenGiven(): void
    {
        $queriedObject = new QueriedObject();
        $queriedObject->setArchive('production');

        $this->assertTrue($queriedObject->isArchive());
        $this->assertTrue($queriedObject->isArchive('production'));
        $this->assertFalse($queriedObject->isArchive('season'));
    }

    public function testSetTermIsAnArchiveButNotAContentTypesArchive(): void
    {
        $queriedObject = new QueriedObject();
        $queriedObject->setTerm('genre', new Term('genre', 'Comedy', 'comedy'));

        $this->assertTrue($queriedObject->isArchive());
        $this->assertFalse($queriedObject->isArchive('work'));
        $this->assertFalse($queriedObject->isSingle());
    }

    public function testIsTaxMatchesAnyTaxonomyThenTaxonomyThenTerm(): void
    {
        $queriedObject = new QueriedObject();
        $queriedObject->setTerm('genre', new Term('genre', 'Comedy', 'comedy'));

        $this->assertTrue($queriedObject->isTax());
        $this->assertTrue($queriedObject->isTax('genre'));
        $this->assertTrue($queriedObject->isTax('genre', 'comedy'));
        $this->assertFalse($queriedObject->isTax('post_category'));
        $this->assertFalse($queriedObject->isTax('genre', 'drama'));
    }

    public function testIsTaxIsFalseOffTermArchives(): void
    {
        $queriedObject = new QueriedObject();
        $this->assertFalse($queriedObject->isTax());

        $queriedObject->setTerm('genre', new Term('genre', 'Comedy', 'comedy'));
        $queriedObject->setSingle('work', $this->makeEntity(1, 'hamlet'));
        $this->assertFalse($queriedObject->isTax());

        $queriedObject->setTerm('genre', new Term('genre', 'Comedy', 'comedy'));
        $queriedObject->setArchive('work');
        $this->assertFalse($queriedObject->isTax());
    }

    public function testGetInstanceThrowsWhenNotInitialized(): void
    {
        $reflection = new \ReflectionProperty(QueriedObject::class, 'instance');
        $reflection->setValue(null, null);

        $this->expectException(\RuntimeException::class);
        QueriedObject::getInstance();
    }

    public function testSetInstanceAndGetInstance(): void
    {
        $queriedObject = new QueriedObject();
        QueriedObject::setInstance($queriedObject);

        $this->assertSame($queriedObject, QueriedObject::getInstance());
    }

    private function makeEntity(int $id, string $slug): object
    {
        return new class ($id, $slug) {
            public function __construct(private readonly int $id, private readonly string $slug)
            {
            }

            public function getId(): int
            {
                return $this->id;
            }

            public function getSlug(): string
            {
                return $this->slug;
            }
        };
    }
}
