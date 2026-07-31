<?php

namespace TheatreCMS\Tests\Unit;

use TheatreCMS\Repositories\VenueRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class TestVenueRepositoryInvalidInput extends TestCase
{
    public function testCreateWithMissingRequiredFieldsThrowsTypeError(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);

        $repo = new VenueRepository($em);

        $this->expectException(\TypeError::class);

        // Missing required constructor params (name, address, etc.) -> TypeError from Venue constructor
        $repo->create([]);
    }

    public function testCreateWithNullNameThrowsTypeError(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);

        $repo = new VenueRepository($em);

        $this->expectException(\TypeError::class);

        // Explicitly pass null for a required field
        $repo->create([
            'name' => null,
            'address' => 'Some Address',
            'city' => 'City',
            'state' => 'ST',
            'postcode' => '00000',
        ]);
    }
}
