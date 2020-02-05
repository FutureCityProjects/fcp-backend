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
trait UpdatedAtFunctions
{
    /**
     * Sets updatedAt.
     *
     * @param  DateTimeImmutable $updatedAt
     * @return $this
     */
    public function setUpdatedAt(DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Returns updatedAt.
     *
     * @return DateTimeImmutable
     */
    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
