<?php

namespace TheatreCMS\Repositories;

use TheatreCMS\Models\Sponsor;

/**
 * @method Sponsor[] query(array $args = [])
 * @method Sponsor[] fetchAll()
 * @method Sponsor|null fetch(int $id)
 */
final class SponsorRepository extends BaseRepository
{
    protected string $entityClass = Sponsor::class;

    public function create(array $args): Sponsor
    {
        $args = array_merge([
            'name'       => null,
            'logoUrl'    => null,
            'websiteUrl' => null,
        ], $args);

        $sponsor = new Sponsor();
        $sponsor->setName($args['name'])
            ->setLogoUrl($args['logoUrl'])
            ->setWebsiteUrl($args['websiteUrl']);

        $sponsor->setSlug($this->generateUniqueSlug($args['name']));
        $this->em->persist($sponsor);
        $this->em->flush();

        return $sponsor;
    }
}

