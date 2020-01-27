<?php
declare(strict_types=1);

namespace App\Doctrine;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\ContextAwareQueryCollectionExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use App\Entity\Fund;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Security\Core\Security;

class FundInactiveExtension implements
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
        if (Fund::class !== $resourceClass) {
            // we are not responsible...
            return;
        }

        // do nothing if Admin or PO
        // @todo this causes logged in admins/POs to see inactive funds
        // on public pages -> add custom filter which shows inactive too and
        // disable them here per default for all users
        if ($this->security->isGranted(User::ROLE_ADMIN)
            || $this->security->isGranted(User::ROLE_PROCESS_OWNER)
        ) {
            return;
        }

        // in all other cases we only return non-locked projects
        $this->addQueryRestriction($queryBuilder);
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
        if (Fund::class !== $resourceClass) {
            return;
        }

        // admins|POs can see inactive funds -> do nothing
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
        $queryBuilder
            ->andWhere(sprintf('%s.state != :inactive', $rootAlias))
            ->setParameter('inactive', Fund::STATE_INACTIVE);
    }
}
