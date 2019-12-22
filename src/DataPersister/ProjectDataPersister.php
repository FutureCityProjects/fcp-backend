<?php
declare(strict_types=1);

namespace App\DataPersister;

use ApiPlatform\Core\Bridge\Doctrine\Common\DataPersister;
use ApiPlatform\Core\DataPersister\ContextAwareDataPersisterInterface;
use ApiPlatform\Core\Exception\InvalidResourceException;
use App\Entity\Project;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * Handles persisting soft-deletes.
 */
class ProjectDataPersister implements ContextAwareDataPersisterInterface
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
        return $data instanceof Project;
    }

    /**
     * {@inheritdoc}
     *
     * @param Project $data
     */
    public function persist($data, array $context = [])
    {
        return $this->wrapped->persist($data, $context);
    }

    /**
     * {@inheritdoc}
     *
     * @param Project $data
     * @throws InvalidResourceException when the project is already marked as deleted
     */
    public function remove($data, array $context = [])
    {
        if ($data->isDeleted()) {
            throw new InvalidResourceException('Project already deleted');
        }

        $data->markDeleted();
        $this->wrapped->persist($data, $context);
    }
}
