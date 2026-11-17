<?php

namespace TheatreCMS\Tests\Unit;

use TheatreCMS\Repositories\ContentMetaRepository;
use TheatreCMS\Repositories\VenueRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class VenueRepositoryInvalidInputTest extends TestCase
{
    public function testCreateWithMissingRequiredFieldsThrows(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);

        $repo = new VenueRepository($em, $this->createStub(ContentMetaRepository::class));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A venue requires: name, address, city, state, postcode.');

        $repo->create([]);
    }

    public function testCreateWithNullOrBlankNameThrows(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);

        $repo = new VenueRepository($em, $this->createStub(ContentMetaRepository::class));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A venue requires: name.');

        $repo->create([
            'name' => '   ',
            'address' => 'Some Address',
            'city' => 'City',
            'state' => 'ST',
            'postcode' => '00000',
        ]);
    }
}
