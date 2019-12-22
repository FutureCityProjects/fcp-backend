<?php
declare(strict_types=1);

namespace App\Entity\Traits;

use DateTimeImmutable;

trait DeletedAtFunctions
{
    /**
     * Sets deletedAt.
     *
     * @param DateTimeImmutable|null $deletedAt
     *
     * @return $this
     */
    public function setDeletedAt(?DateTimeImmutable $deletedAt): self
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    /**
     * Returns deletedAt.
     *
     * @return DateTimeImmutable
     */
    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    /**
     * Is deleted?
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return null !== $this->deletedAt;
    }

    /**
     * Sets the deletedAt timestamp to mark the object as deleted
     *
     * @return $this
     */
    public function markDeleted(): self
    {
        $this->deletedAt = new DateTimeImmutable();

        return $this;
    }
}
