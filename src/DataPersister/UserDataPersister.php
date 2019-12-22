<?php
declare(strict_types=1);

namespace App\DataPersister;

use ApiPlatform\Core\Bridge\Doctrine\Common\DataPersister;
use ApiPlatform\Core\DataPersister\ContextAwareDataPersisterInterface;
use ApiPlatform\Core\Exception\InvalidResourceException;
use App\Entity\User;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * Handles persisting soft-deletes.
 */
class UserDataPersister implements ContextAwareDataPersisterInterface
{
    /**
     * @var DataPersister
     */
    protected $wrapped;

    public function __construct(ManagerRegistry $managerRegistry)
    {
        $this->wrapped = new DataPersister($managerRegistry);
    }

    /**
     * {@inheritdoc}
     */
    public function supports($data, array $context = []): bool
    {
        return $data instanceof User;
    }

    /**
     * {@inheritdoc}
     *
     * @param User $data
     */
    public function persist($data, array $context = [])
    {
        return $this->wrapped->persist($data, $context);
    }

    /**
     * {@inheritdoc}
     *
     * @param User $data
     * @throws InvalidResourceException when the user is already marked as deleted
     */
    public function remove($data, array $context = [])
    {
        if ($data->isDeleted()) {
            throw new InvalidResourceException('User already deleted');
        }

        $data->markDeleted();
        $this->wrapped->persist($data, $context);
    }
}
