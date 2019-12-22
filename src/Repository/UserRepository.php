<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements UserLoaderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findNonDeleted(int $id): ?User
    {
        return $this->findOneBy([
            'deletedAt' => null,
            'id'        => $id,
        ]);
    }

    public function findOneNonDeletedBy(array $criteria): ?User
    {
        $criteria['deletedAt'] = null;
        return $this->findOneBy($criteria);
    }

    public function findNonDeletedBy(array $criteria): array
    {
        $criteria['deletedAt'] = null;
        return $this->findBy($criteria);
    }

    /**
     * For the UserLoaderInterface: This allows us to login the user via his
     * username or email, the method is automatically by Symfony when the key
     * security.providers.app_user_provider.entity.property is NOT set. This
     * replaces the need for a custom GuardAuthenticator. All other checks for
     * the user are done in our own Security\UserChecker.
     *
     * Attention: Requires that email addresses and also usernames are unique
     * and also no user may have a username equal to the email of another user.
     *
     * @param string $identifier
     * @return \Symfony\Component\Security\Core\User\UserInterface|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function loadUserByUsername($identifier)
    {
        if ($identifier == AuthenticationProviderInterface::USERNAME_NONE_PROVIDED) {
            return null;
        }

        return $this->getEntityManager()->createQuery(
            'SELECT u
                FROM App\Entity\User u
                WHERE
                    (u.username = :query OR u.email = :query)
                    AND u.deletedAt IS NULL'
            )
            ->setParameter('query', $identifier)
            ->getOneOrNullResult();
    }
}
