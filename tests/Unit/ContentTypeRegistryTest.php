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

    public function testANewRegistryHasNoTypes(): void
    {
        $this->assertSame([], (new ContentTypeRegistry())->all());
    }

    public function testDefaultPrefixMatchesTypeName(): void
    {
        $registry = new ContentTypeRegistry();
        $registry->register('season');

        $this->assertSame('season', $registry->prefix('season'));
    }

    public function testOverridePrefixFromConfig(): void
    {
        $registry = new ContentTypeRegistry(['season' => ['urlPrefix' => 'shows']]);
        $registry->register('season', ['urlPrefix' => 'seasons']);

        $this->assertSame('shows', $registry->prefix('season'));
    }

    public function testDefaultLabelIsThePluralOfTheCapitalizedTypeName(): void
    {
        $registry = new ContentTypeRegistry();
        $registry->register('season');

        $this->assertSame('Seasons', $registry->label('season'));
    }

    public function testDefaultSingularLabelComesFromTheNameNotTheLabel(): void
    {
        $definition = (new ContentTypeRegistry())->register('media_gallery', ['label' => 'Media Galleries']);

        $this->assertSame('Media Galleries', $definition->label);
        $this->assertSame('Media Gallery', $definition->singularLabel);
    }

    public function testDefaultLabelIsThePluralOfTheSpecifiedSingularLabel(): void
    {
        $definition = (new ContentTypeRegistry())->register('video_clip', ['singularLabel' => 'Video']);

        $this->assertSame('Videos', $definition->label);
    }

    public function testExpandedOverrideSetsIndependentLabel(): void
    {
        $registry = new ContentTypeRegistry([
            'season' => ['urlPrefix' => 'shows', 'label' => 'Our Shows'],
        ]);
        $registry->register('season', ['urlPrefix' => 'seasons']);

        $this->assertSame('shows', $registry->prefix('season'));
        $this->assertSame('Our Shows', $registry->label('season'));
    }

    public function testArchiveEnabledByDefault(): void
    {
        $registry = new ContentTypeRegistry();
        $registry->register('season', ['urlPrefix' => 'seasons']);

        $this->assertTrue($registry->hasArchive('season'));
    }

    public function testArchiveDisabledFromConfig(): void
    {
        $registry = new ContentTypeRegistry([
            'season' => ['hasArchive' => false],
        ]);
        $registry->register('season', ['urlPrefix' => 'seasons']);

        $this->assertFalse($registry->hasArchive('season'));
    }

    public function testArchiveDisabledAlongsideUrlPrefixOverride(): void
    {
        $registry = new ContentTypeRegistry([
            'season' => ['urlPrefix' => 'shows', 'hasArchive' => false],
        ]);
        $registry->register('season', ['urlPrefix' => 'seasons']);

        $this->assertSame('shows', $registry->prefix('season'));
        $this->assertFalse($registry->hasArchive('season'));
    }

    public function testStringOverrideThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ContentTypeRegistry(['season' => 'shows']);
    }

    public function testUnknownOverrideKeyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ContentTypeRegistry(['season' => ['url_prefix' => 'shows']]);
    }

    public function testInvalidOverridePrefixThrowsWhenTheTypeRegisters(): void
    {
        $registry = new ContentTypeRegistry(['season' => ['urlPrefix' => 'admin']]);

        $this->expectException(InvalidArgumentException::class);
        $registry->register('season', ['urlPrefix' => 'seasons']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function lookupMethods(): array
    {
        return [
            'prefix' => ['prefix'],
            'label' => ['label'],
            'hasArchive' => ['hasArchive'],
        ];
    }

    #[DataProvider('lookupMethods')]
    public function testLookupThrowsForAnUnregisteredType(string $method): void
    {
        $registry = new ContentTypeRegistry();
        $registry->register('season', ['urlPrefix' => 'seasons']);

        $this->expectException(InvalidArgumentException::class);
        $registry->$method('seasons');
    }

    public function testHasForUnknownTypeReturnsFalse(): void
    {
        $registry = new ContentTypeRegistry();

        $this->assertFalse($registry->has('foo'));
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
        $this->assertSame('Press Releases', $definition->label, 'Label');
        $this->assertSame('Press Release', $definition->singularLabel, 'Singular Label');
    }

    public function testRegisterUsesDefinitionDefaultsForOtherFields(): void
    {
        $definition = (new ContentTypeRegistry())->register('press_release');

        $this->assertTrue($definition->hasArchive);
        $this->assertTrue($definition->public);
        $this->assertSame(['editor'], $definition->supports);
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
        $registry->register('season', ['urlPrefix' => 'seasons']);

        $this->expectException(InvalidArgumentException::class);

        $registry->register('press_release', ['urlPrefix' => 'seasons']);
    }

    public function testRegisterFailureLeavesTheTypeUnregistered(): void
    {
        $registry = new ContentTypeRegistry();
        $registry->register('season', ['urlPrefix' => 'seasons']);

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
