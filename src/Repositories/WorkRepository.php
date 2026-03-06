<?php

namespace Clubdeuce\TheatreCMS\Repositories;

use Clubdeuce\TheatreCMS\Models\Person;
use Clubdeuce\TheatreCMS\Models\Work;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;

class WorkRepository extends BaseRepository
{
    protected string $entityClass = Work::class;

    public function create(array $args): bool|Work
    {
        $work = new Work();

        $work->setTitle($args['title'])
            ->setDescription($args['description'] ?? '')
            ->setSynopsis($args['synopsis'] ?? '');

        $work->setSlug($this->generateUniqueSlug($work->getTitle()));

        // Attach creators if provided. Each creator may be either:
        // - a numeric person ID
        // - an array/object with keys: personId | person_id | id and optional 'role'
        if (!empty($args['creators']) && is_array($args['creators'])) {
            foreach ($args['creators'] as $entry) {
                $normalized = $this->normalizeCreatorEntry($entry);
                $personId = intval($normalized['id'] ?? 0);
                $role = $normalized['role'] ?? '';

                if (!$personId) {
                    continue;
                }

                $person = $this->em->getRepository(Person::class)->find($personId);
                if ($person) {
                    $work->addCreator($person, $role);
                }
            }
        }

        $this->em->persist($work);
        $this->em->flush();
        return $work;
    }

    /**
     * Replace the creators collection on a work with the provided person IDs or creator entries.
     * Invalid or non-existing person IDs are ignored.
     *
     * Acceptable entry shapes: integer person id OR array/object with id and optional role.
     */
    public function setCreators(Work $work, array $creatorEntries): Work
    {
        // Clear current workCreators using public API
        $work->clearWorkCreators();

        foreach ($creatorEntries as $entry) {
            $normalized = $this->normalizeCreatorEntry($entry);
            $personId = intval($normalized['id'] ?? 0);
            $role = $normalized['role'] ?? '';

            if (!$personId) {
                continue;
            }

            $person = $this->em->getRepository(Person::class)->find($personId);
            if ($person) {
                $work->addCreator($person, $role);
            }
        }

        $this->em->persist($work);
        $this->em->flush();

        return $work;
    }

    /**
     * Update a Work entity from an args array. This will update title, description, synopsis
     * and (optionally) replace the creators collection.
     */
    public function updateFromArgs(Work $work, array $args): Work
    {
        if (isset($args['title'])) {
            $work->setTitle($args['title']);
        }

        if (array_key_exists('description', $args)) {
            $work->setDescription($args['description'] ?? '');
        }

        if (array_key_exists('synopsis', $args)) {
            $work->setSynopsis($args['synopsis'] ?? '');
        }

        if (isset($args['creators']) && is_array($args['creators'])) {
            // Replace creators
            $work = $this->setCreators($work, $args['creators']);
        } else {
            // Persist any other changes
            $this->em->persist($work);
            $this->em->flush();
        }

        return $work;
    }

    /**
     * Normalize a creator entry into an array with keys 'id' and optional 'role'.
     * Supports integer entries, arrays, and objects.
     *
     * @param mixed $entry
     * @return array{ id?: int, role?: string }
     */
    private function normalizeCreatorEntry(mixed $entry): array
    {
        $result = ['id' => 0, 'role' => ''];

        if (is_int($entry) || (is_string($entry) && ctype_digit($entry))) {
            $result['id'] = intval($entry);
            return $result;
        }

        if (is_array($entry)) {
            if (!empty($entry['personId'])) {
                $result['id'] = intval($entry['personId']);
            } elseif (!empty($entry['person_id'])) {
                $result['id'] = intval($entry['person_id']);
            } elseif (!empty($entry['id'])) {
                $result['id'] = intval($entry['id']);
            }

            if (isset($entry['role'])) {
                $result['role'] = strval($entry['role']);
            }

            return $result;
        }

        if (is_object($entry)) {
            // Try common property names
            if (isset($entry->personId)) {
                $result['id'] = intval($entry->personId);
            } elseif (isset($entry->person_id)) {
                $result['id'] = intval($entry->person_id);
            } elseif (isset($entry->id)) {
                $result['id'] = intval($entry->id);
            }

            if (isset($entry->role)) {
                $result['role'] = strval($entry->role);
            }

            return $result;
        }

        return $result;
    }
}
