<?php

namespace TheatreCMS\Models;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\Table;

#[Entity, Table(name: 'works')]
class Work extends ModelBase implements \JsonSerializable
{
    #[Id, Column(type: 'integer'), GeneratedValue(strategy: 'AUTO')]
    private int $id;

    #[Column(type: 'string', nullable: false)]
    private string $title;

    #[Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[Column(type: 'text', nullable: true)]
    private ?string $synopsis = null;

    // OneToMany relation to WorkCreator entities which link to Person with role
    #[OneToMany(targetEntity: WorkCreator::class, mappedBy: 'work', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $workCreators;

    public function __construct()
    {
        $this->workCreators = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description ?? '';
    }

    /**
     * Return a Collection of Person objects who are creators of this Work.
     *
     * @return Collection<int, Person>
     */
    public function getCreators(): Collection
    {
        return $this->workCreators->map(fn(WorkCreator $wc) => $wc->person());
    }

    /**
     * Return the underlying WorkCreator entities collection. Useful when you need roles.
     *
     * @return Collection<int, WorkCreator>
     */
    public function getWorkCreators(): Collection
    {
        return $this->workCreators;
    }

    /**
     * Clear all WorkCreator entries from this Work.
     */
    public function clearWorkCreators(): self
    {
        $this->workCreators->clear();
        return $this;
    }

    public function getSynopsis(): string
    {
        return $this->synopsis ?? '';
    }

    /**
     * Add a creator Person to this Work. Optionally provide a role which is stored on the WorkCreator entity.
     */
    public function addCreator($creator, string $role = ''): self
    {
        // If a Person instance is passed, create a WorkCreator wrapper
        if ($creator instanceof Person) {
            // ensure we don't add duplicates by person id (if available)
            foreach ($this->workCreators as $wc) {
                if ($wc->person()->getId() === $creator->getId()) {
                    return $this;
                }
            }

            $workCreator = new WorkCreator($this, $creator, $role);
            $this->workCreators->add($workCreator);
        }

        return $this;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function setSynopsis(?string $synopsis): self
    {
        $this->synopsis = $synopsis;
        return $this;
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
