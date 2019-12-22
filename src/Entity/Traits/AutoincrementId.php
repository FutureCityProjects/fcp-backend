<?php
declare(strict_types=1);

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Identifier property for Doctrine entities.
 *
 * Serializer group annotation "default:read" so it is readable in all entities.
 */
trait AutoincrementId
{
    /**
     * @var int
     *
     * @Groups({"default:read"})
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
