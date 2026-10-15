<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Auth\Capability;
use TheatreCMS\ContentType\ContentTypeRegistry;
use TheatreCMS\Tests\Includes\CapturesUserNotices;

class ContentTypeRegistryTest extends TestCase
{
    use CapturesUserNotices;

    private const BUILTIN_CONTENT_TYPES = [
        'seasons',
        'productions',
        'works',
        'people',
        'posts',
    ];

    public function testDefaultPrefixMatchesTypeName(): void
    {
        $registry = new ContentTypeRegistry();

        $this->assertSame('seasons', $registry->prefix('seasons'));
    }

    public function testOverridePrefixFromConfig(): void
    {
        $registry = new ContentTypeRegistry(['seasons' => ['urlPrefix' => 'shows']]);

        $this->assertSame('shows', $registry->prefix('seasons'));
    }

    public function testUnknownTypePassesThroughUnchanged(): void
    {
        $registry = new ContentTypeRegistry();

        $this->assertSame('widgets', $registry->prefix('widgets'));
    }

    public function testDefaultLabelMatchesCapitalizedTypeName(): void
    {
        $registry = new ContentTypeRegistry();

        $this->assertSame('Seasons', $registry->label('seasons'));
    }

    public function testExpandedOverrideSetsIndependentLabel(): void
    {
        $registry = new ContentTypeRegistry([
            'seasons' => ['urlPrefix' => 'shows', 'label' => 'Our Shows'],
        ]);

        $this->assertSame('shows', $registry->prefix('seasons'));
        $this->assertSame('Our Shows', $registry->label('seasons'));
    }

    public function testUnknownTypeLabelFallsBackToCapitalizedType(): void
    {
        $registry = new ContentTypeRegistry();

        $this->assertSame('Widgets', $registry->label('widgets'));
    }

    public function testArchiveEnabledByDefault(): void
    {
        $registry = new ContentTypeRegistry();

        $this->assertTrue($registry->hasArchive('seasons'));
        $this->assertTrue($registry->hasArchive('widgets'));
    }

    public function testArchiveDisabledFromConfig(): void
    {
        $registry = new ContentTypeRegistry([
            'seasons' => ['hasArchive' => false],
        ]);

        $this->assertFalse($registry->hasArchive('seasons'));
    }

    public function testArchiveDisabledAlongsideUrlPrefixOverride(): void
    {
        $registry = new ContentTypeRegistry([
            'seasons' => ['urlPrefix' => 'shows', 'hasArchive' => false],
        ]);

        $this->assertSame('shows', $registry->prefix('seasons'));
        $this->assertFalse($registry->hasArchive('seasons'));
    }

    public function testStringOverrideThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ContentTypeRegistry(['seasons' => 'shows']);
    }

    public function testUnknownOverrideKeyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ContentTypeRegistry(['seasons' => ['url_prefix' => 'shows']]);
    }

    public function testHasForBuiltInTypes(): void
    {
        $registry = new ContentTypeRegistry();

        foreach (self::BUILTIN_CONTENT_TYPES as $contentType) {
            $this->assertTrue($registry->has($contentType), "Unknown content type: {$contentType}");
        }
    }

    public function testHasForUnknownTypeReturnsFalse(): void
    {
        $registry = new ContentTypeRegistry();

        $this->assertFalse($registry->has('foo'));
    }

    public function testRequireForBuiltInTypes(): void
    {
        $registry = new ContentTypeRegistry();

        foreach (self::BUILTIN_CONTENT_TYPES as $type) {
            $this->assertSame($type, $registry->require($type)->name);
        }
    }

    public function testRequireForUnknownTypeThrowsError(): void
    {
        $registry = new ContentTypeRegistry();

        $this->expectException(InvalidArgumentException::class);
        $registry->require('foo');
    }

    // register() (P1-01 step 2d)

    public function testRegisterAddsTheType(): void
    {
        $registry = new ContentTypeRegistry();

        $definition = $registry->register('press_release');

        $this->assertSame('press_release', $definition->name);
        $this->assertTrue($registry->has('press_release'));
        $this->assertSame($definition, $registry->get('press_release'));
    }

    public function testRegisterDerivesUrlPrefixAndLabelsFromTheName(): void
    {
        $definition = (new ContentTypeRegistry())->register('press_release');

        $this->assertSame('press-release', $definition->urlPrefix, 'Prefix');
        $this->assertSame('Press Release', $definition->label, 'Label');
        $this->assertSame('Press Release', $definition->singularLabel, 'Singular Label');
    }

    public function testRegisterUsesDefinitionDefaultsForOtherFields(): void
    {
        $definition = (new ContentTypeRegistry())->register('press_release');

        $this->assertTrue($definition->hasArchive);
        $this->assertTrue($definition->public);
        $this->assertSame([], $definition->supports);
        $this->assertSame(Capability::EDIT_POSTS, $definition->capability);
    }

    public function testRegisterAppliesEveryArg(): void
    {
        $definition = (new ContentTypeRegistry())->register('press_release', [
            'label' => 'Press Releases',
            'singularLabel' => 'Press Release',
            'urlPrefix' => 'press',
            'hasArchive' => false,
            'public' => false,
            'supports' => ['editor', 'excerpt'],
            'capability' => Capability::MANAGE_OPTIONS,
        ]);

        $this->assertSame('Press Releases', $definition->label, 'Label not as expected');
        $this->assertSame('Press Release', $definition->singularLabel, 'Singular label not as expected');
        $this->assertSame('press', $definition->urlPrefix, 'urlPrefix not as expected');
        $this->assertFalse($definition->hasArchive, 'hasArchive not set as expected');
        $this->assertFalse($definition->public, 'public not set as expected');
        $this->assertSame(['editor', 'excerpt'], $definition->supports);
        $this->assertSame(Capability::MANAGE_OPTIONS, $definition->capability);
    }

    public function testRegisteredTypeIsUsedByTheLookupMethods(): void
    {
        $registry = new ContentTypeRegistry();
        $registry->register('press_release', ['label' => 'Press Releases', 'urlPrefix' => 'press']);

        $this->assertSame('press', $registry->prefix('press_release'));
        $this->assertSame('Press Releases', $registry->label('press_release'));
        $this->assertSame('press_release', $registry->require('press_release')->name);
        $this->assertArrayHasKey('press_release', $registry->all());
    }

    public function testRegisterAcceptsNamesLongerThan32Characters(): void
    {
        $name = 'educational_outreach_program_resource';

        $this->assertGreaterThan(32, strlen($name));
        $this->assertSame($name, (new ContentTypeRegistry())->register($name)->name);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidTypeNames(): array
    {
        return [
            'empty' => [''],
            'uppercase' => ['PressRelease'],
            'digit' => ['release_2026'],
            'hyphen' => ['press-release'],
        ];
    }

    #[DataProvider('invalidTypeNames')]
    public function testRegisterRejectsInvalidName(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ContentTypeRegistry())->register($name);
    }

    public function testRegisterRejectsABuiltInName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ContentTypeRegistry())->register('seasons');
    }

    public function testRegisterRejectsANameRegisteredTwice(): void
    {
        $registry = new ContentTypeRegistry();
        $registry->register('press_release');

        $this->expectException(InvalidArgumentException::class);

        $registry->register('press_release');
    }

    public function testRegisterIgnoresUnknownArgsWithOneNoticeEach(): void
    {
        $registry = new ContentTypeRegistry();

        [$definition, $levels] = $this->captureUserNotices(
            static fn () => $registry->register('press_release', ['url_prefix' => 'press', 'urlprefix' => 'press'])
        );

        $this->assertSame([E_USER_NOTICE, E_USER_NOTICE], $levels);
        $this->assertSame('press-release', $definition->urlPrefix);
        $this->assertTrue($registry->has('press_release'));
    }

    public function testRegisterRejectsAnInvalidUrlPrefix(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ContentTypeRegistry())->register('press_release', ['urlPrefix' => 'admin']);
    }

    public function testRegisterRejectsAPrefixAnotherContentTypeUses(): void
    {
        $registry = new ContentTypeRegistry();

        $this->expectException(InvalidArgumentException::class);

        $registry->register('press_release', ['urlPrefix' => 'seasons']);
    }

    public function testRegisterFailureLeavesTheTypeUnregistered(): void
    {
        $registry = new ContentTypeRegistry();

        try {
            $registry->register('press_release', ['urlPrefix' => 'seasons']);
            $this->fail('Expected the prefix collision to throw.');
        } catch (InvalidArgumentException) {
            $this->assertFalse($registry->has('press_release'));
        }
    }

    public function testConfigOverrideAppliesToARegisteredType(): void
    {
        $registry = new ContentTypeRegistry([
            'press_release' => ['urlPrefix' => 'news', 'label' => 'News', 'hasArchive' => false],
        ]);

        $definition = $registry->register('press_release');

        $this->assertSame('news', $definition->urlPrefix);
        $this->assertSame('News', $definition->label);
        $this->assertFalse($definition->hasArchive);
    }

    public function testConfigOverrideWinsOverRegisterArgs(): void
    {
        $registry = new ContentTypeRegistry(['press_release' => ['urlPrefix' => 'news']]);

        $definition = $registry->register('press_release', ['urlPrefix' => 'press', 'label' => 'Press']);

        $this->assertSame('news', $definition->urlPrefix);
        $this->assertSame('Press', $definition->label);
    }
}
