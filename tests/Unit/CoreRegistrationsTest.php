<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TheatreCMS\ContentType\ContentTypeRegistry;
use TheatreCMS\Routing\UrlPrefixRegistry;
use TheatreCMS\Taxonomy\TaxonomyRegistry;

/**
 * Core's own content types and taxonomies, registered at bootstrap from app/core-*.php.
 */
class CoreRegistrationsTest extends TestCase
{
    private const APP_DIR = __DIR__ . '/../../app';

    public static function setUpBeforeClass(): void
    {
        require_once self::APP_DIR . '/content-types.php';
        require_once self::APP_DIR . '/taxonomies.php';
    }

    public function testCoreContentTypesFileRegistersTheBuiltIns(): void
    {
        $registry = $this->registerCoreContentTypes();

        $this->assertSame(['season', 'production', 'person', 'work', 'post'], array_keys($registry->all()));
        $this->assertSame('people', $registry->prefix('person'));
        $this->assertSame('People', $registry->label('person'));
        $this->assertSame('Person', $registry->require('person')->singularLabel);
    }

    public function testConfigOverrideAppliesToACoreContentType(): void
    {
        $registry = $this->registerCoreContentTypes(['season' => ['urlPrefix' => 'shows']]);

        $this->assertSame('shows', $registry->prefix('season'));
    }

    public function testCoreTaxonomiesFileRegistersGenreAndPostCategory(): void
    {
        $registry = new TaxonomyRegistry();
        TaxonomyRegistry::setInstance($registry);

        require self::APP_DIR . '/core-taxonomies.php';

        $this->assertSame(['genre', 'post_category'], array_keys($registry->all()));
        $this->assertSame('category', $registry->require('post_category')->urlPrefix);
    }

    public function testCoreContentTypesAndTaxonomiesCanShareOneSetOfPrefixes(): void
    {
        $urlPrefixes = new UrlPrefixRegistry();
        $this->registerCoreContentTypes([], $urlPrefixes);
        TaxonomyRegistry::setInstance(new TaxonomyRegistry($urlPrefixes));

        require self::APP_DIR . '/core-taxonomies.php';

        $this->assertTrue(TaxonomyRegistry::getInstance()->has('genre'));
    }

    /**
     * @param array<string, array<string, mixed>> $overrides
     */
    private function registerCoreContentTypes(
        array $overrides = [],
        ?UrlPrefixRegistry $urlPrefixes = null
    ): ContentTypeRegistry {
        $registry = new ContentTypeRegistry($overrides, $urlPrefixes);
        ContentTypeRegistry::setInstance($registry);

        require self::APP_DIR . '/core-content-types.php';

        return $registry;
    }
}
