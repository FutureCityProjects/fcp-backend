<?php
declare(strict_types=1);

namespace App\Entity\Traits;

trait NameFunctions
{
    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }
}
