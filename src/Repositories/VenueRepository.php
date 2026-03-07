<?php

namespace TheatreCMS\Repositories;

use TheatreCMS\Models\Venue;

/**
 * @method Venue[] query(array $args = [])
 * @method Venue[] fetchAll()
 * @method Venue|null fetch(int $id)
 */
final class VenueRepository extends BaseRepository
{
    protected string $entityClass = Venue::class;

    public function create(array $args): Venue
    {
        $args = array_merge([
            'name' => null,
            'address' => null,
            'city' => null,
            'state' => null,
            'postcode' => null,
            'capacity' => null,
            'description' => null,
            'accessibilityInfo' => null,
            'websiteUrl' => null,
            'mapUrl' => null,
        ], $args);

        $venue = new Venue(
            $args['name'],
            $args['address'],
            $args['city'],
            $args['state'],
            $args['postcode']
        );

        $venue->setCapacity(intval($args['capacity']))
            ->setDescription($args['description'])
            ->setAccessibilityInfo($args['accessibilityInfo'])
            ->setWebsiteUrl($args['websiteUrl'])
            ->setMapUrl($args['mapUrl'])
            ->setSlug($this->generateUniqueSlug($venue->getName()));

        $this->em->persist($venue);
        $this->em->flush();

        return $venue;
    }
}
