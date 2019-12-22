<?php
declare(strict_types=1);

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

trait RequiredName
{
    /**
     * @var string
     *
     * @Assert\NotBlank
     * @Groups({"default:read", "default:write", "elastica"})
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    private ?string $name = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }
}
