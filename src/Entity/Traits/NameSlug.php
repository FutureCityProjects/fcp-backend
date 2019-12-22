<?php
declare(strict_types=1);

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

trait NameSlug
{
    /**
     * @var string
     *
     * @Assert\NotBlank(allowNull=true)
     * @Groups({"default:read", "elastica"})
     * @ORM\Column(type="string", length=255, nullable=false, unique=true)
     * @Gedmo\Slug(fields={"name"})
     */
    private ?string $slug = null;

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }
}
