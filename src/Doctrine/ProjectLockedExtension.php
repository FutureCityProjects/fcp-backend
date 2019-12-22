<?php
declare(strict_types=1);

namespace App\Doctrine;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\ContextAwareQueryCollectionExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Security\Core\Security;

class ProjectLockedExtension implements
    ContextAwareQueryCollectionExtensionInterface,
    QueryItemExtensionInterface
{
    /**
     * @var Security
     */
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    /**
     * For ContextAwareQueryCollectionExtensionInterface
     *
     * {@inheritdoc}
     */
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        string $operationName = null,
        array $context = []
    ) {
        if (Project::class !== $resourceClass) {
            // we are not responsible...
            return;
        }

        // if an admin|PO filtered explicitly do nothing, else enforce only
        // non-locked projects
        if (isset($context['filters']['isLocked'])
            && ($this->security->isGranted(User::ROLE_ADMIN)
            || $this->security->isGranted(User::ROLE_PROCESS_OWNER))
        ) {
            return;
        }

        // logged in users can filter by ID and retrieve locked projects,
        // e.g. members filter by the IDs of their projects
        if (isset($context['filters']['id']) &&
            $this->security->isGranted(User::ROLE_USER)
        ) {
            return;
        }

        // in all other cases we only return non-locked projects
        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder
            ->andWhere(sprintf('%s.isLocked = :isLocked', $rootAlias))
            ->setParameter('isLocked', false);
    }

    /**
     * For QueryItemExtensionInterface
     *
     * @param QueryBuilder $queryBuilder
     * @param QueryNameGeneratorInterface $queryNameGenerator
     * @param string $resourceClass
     * @param array $identifiers
     * @param string|null $operationName
     * @param array $context
     */
    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        string $operationName = null,
        array $context = []
    ) {
        if (Project::class !== $resourceClass) {
            return;
        }

        // admins|POs can see locked projects -> do nothing
        if ($this->security->isGranted(User::ROLE_ADMIN)
            || $this->security->isGranted(User::ROLE_PROCESS_OWNER)
        ) {
            return;
        }

        $this->addQueryRestriction($queryBuilder);
    }

    protected function addQueryRestriction(QueryBuilder $queryBuilder)
    {
        $rootAlias = $queryBuilder->getRootAliases()[0];

        // members/owners can see locked projects
        if ($this->security->getUser()) {
            $queryBuilder
                ->leftJoin("$rootAlias.memberships", "memberships")
                ->andWhere("($rootAlias.isLocked = :isLocked OR (" .
                    "memberships.role IN (:memberRoles) " .
                    "AND memberships.user = :currentUser" .
                    "))")
                ->setParameter('isLocked', false)
                ->setParameter('memberRoles',
                    [ProjectMembership::ROLE_OWNER, ProjectMembership::ROLE_MEMBER])
                ->setParameter('currentUser', $this->security->getUser());
        }
        // enforce restriction for all other users
        else {
            $queryBuilder
                ->andWhere(sprintf('%s.isLocked = :isLocked', $rootAlias))
                ->setParameter('isLocked', false);
        }
    }
}
