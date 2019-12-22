<?php
declare(strict_types=1);

namespace App\Entity\Traits;

use DateTimeImmutable;

/**
 * Timestampable property for Doctrine entities.
 *
 * Serializer group annotation "default:read" so it is readable in all entities.
 * NotBlank allowNull so API Platform does not complain when no value is submitted.
 */
trait CreatedAtFunctions
{
    /**
     * Sets createdAt.
     *
     * @param  DateTimeImmutable $createdAt
     * @return $this
     */
    public function setCreatedAt(DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Returns createdAt.
     *
     * @return DateTimeImmutable
     */
    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }
}
