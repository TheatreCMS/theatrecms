<?php

namespace TheatreCMS\Tests\Unit;

use TheatreCMS\Models\Organization;
use PHPUnit\Framework\TestCase;

/**
 * Class TestOrganization
 * @package TheatreCMS\Tests\Unit
 *
 * @coversDefaultClass \TheatreCMS\Models\Organization
 */
class TestOrganization extends TestCase
{
    public function testConstructor(): void
    {
        $organization = new Organization('Test Theatre', 'test-theatre');
        
        $this->assertEquals('Test Theatre', $organization->getName());
        $this->assertEquals('test-theatre', $organization->getSlug());
        
        // Test with defaults
        $emptyOrg = new Organization();
        $this->assertEquals('', $emptyOrg->getName());
        $this->assertEquals('', $emptyOrg->getSlug());
    }

    public function testGettersAndSetters(): void
    {
        $organization = new Organization();

        $this->assertEquals(0, $organization->getId());

        $organization->setName('Theatre Company');
        $this->assertEquals('Theatre Company', $organization->getName());

        $organization->setSlug('theatre-company');
        $this->assertEquals('theatre-company', $organization->getSlug());

        $organization->setMissionStatement('Our mission is to promote performing arts');
        $this->assertEquals('Our mission is to promote performing arts', $organization->getMissionStatement());

        $organization->setFoundedYear(1985);
        $this->assertEquals(1985, $organization->getFoundedYear());

        $organization->setLogoUrl('https://example.com/logo.png');
        $this->assertEquals('https://example.com/logo.png', $organization->getLogoUrl());

        $organization->setWebsiteUrl('https://example.com');
        $this->assertEquals('https://example.com', $organization->getWebsiteUrl());

        $socialLinks = [
            'facebook' => 'https://facebook.com/theatre',
            'twitter' => 'https://twitter.com/theatre',
        ];
        $organization->setSocialLinks($socialLinks);
        $this->assertEquals($socialLinks, $organization->getSocialLinks());

        $organization->setAddress('123 Main St, City, State 12345');
        $this->assertEquals('123 Main St, City, State 12345', $organization->getAddress());
    }

    public function testNullableFields(): void
    {
        $organization = new Organization();
        
        $this->assertNull($organization->getMissionStatement());
        $this->assertNull($organization->getFoundedYear());
        $this->assertNull($organization->getLogoUrl());
        $this->assertNull($organization->getWebsiteUrl());
        $this->assertNull($organization->getSocialLinks());
        $this->assertNull($organization->getAddress());

        $organization->setMissionStatement(null);
        $organization->setFoundedYear(null);
        $organization->setLogoUrl(null);
        $organization->setWebsiteUrl(null);
        $organization->setSocialLinks(null);
        $organization->setAddress(null);

        $this->assertNull($organization->getMissionStatement());
        $this->assertNull($organization->getFoundedYear());
        $this->assertNull($organization->getLogoUrl());
        $this->assertNull($organization->getWebsiteUrl());
        $this->assertNull($organization->getSocialLinks());
        $this->assertNull($organization->getAddress());
    }

    public function testJsonSerialize(): void
    {
        $organization = new Organization();
        $organization->setName('Test Theatre');
        $organization->setSlug('test-theatre');
        $organization->setFoundedYear(2000);

        $json = $organization->jsonSerialize();

        $this->assertIsArray($json);
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('name', $json);
        $this->assertArrayHasKey('slug', $json);
        $this->assertArrayHasKey('missionStatement', $json);
        $this->assertArrayHasKey('foundedYear', $json);
        $this->assertArrayHasKey('logoUrl', $json);
        $this->assertArrayHasKey('websiteUrl', $json);
        $this->assertArrayHasKey('socialLinks', $json);
        $this->assertArrayHasKey('address', $json);

        $this->assertEquals('Test Theatre', $json['name']);
        $this->assertEquals('test-theatre', $json['slug']);
        $this->assertEquals(2000, $json['foundedYear']);
    }

    public function testFluentInterface(): void
    {
        $organization = new Organization();
        
        $result = $organization
            ->setName('Test')
            ->setSlug('test')
            ->setMissionStatement('Mission')
            ->setFoundedYear(2020)
            ->setLogoUrl('logo.png')
            ->setWebsiteUrl('https://test.com')
            ->setSocialLinks(['twitter' => 'test'])
            ->setAddress('123 Test St');

        $this->assertInstanceOf(Organization::class, $result);
        $this->assertSame($organization, $result);
    }
}
