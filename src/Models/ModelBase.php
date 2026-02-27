<?php

namespace Clubdeuce\TheatreCMS\Models;

use Doctrine\ORM\Mapping\Column;

class ModelBase
{
    /**
     * A unique identifier for the model, to be used in URLs and such.
     * @var string
     */
    #[Column(type: "string", unique: true, nullable: false)]
    protected string $slug;

    public function getSlug(): string
    {
        return $this->slug;
    }


    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }
}
