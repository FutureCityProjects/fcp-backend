<?php
declare(strict_types=1);

namespace App\Tests\Entity;

use App\DataFixtures\TestFixtures;
use App\Entity\Process;
use App\Entity\User;
use App\Entity\UserObjectRole;
use App\PHPUnit\RefreshDatabaseTrait;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @group UserObjectRoleEntity
 */
class UserObjectRoleTest extends KernelTestCase
{
    use RefreshDatabaseTrait;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $entityManager;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
    }

    protected function getObjectRoleRepository(): EntityRepository
    {
        return $this->entityManager->getRepository(UserObjectRole::class);
    }

    /**
     * Tests the defaults for new roles.
     */
    public function testCreateAndReadRole(): void
    {
        /** @var $user User */
        $user = $this->entityManager->getRepository(User::class)
            ->find(TestFixtures::PROJECT_MEMBER['id']);

        $before = $this->getObjectRoleRepository()->findBy(['user' => $user]);
        $this->assertCount(0, $before);

        $role = new UserObjectRole();
        $role->setRole(UserObjectRole::ROLE_PROCESS_OWNER);
        $role->setObjectType(Process::class);
        $role->setObjectId(1);
        $user->addObjectRole($role);

        $this->entityManager->persist($role);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $byUser = $this->getObjectRoleRepository()->findBy(['user' => $user]);
        $this->assertCount(1, $byUser);

        $byProcess = $this->getObjectRoleRepository()->findBy([
            'objectType' => Process::class,
            'objectId'   => 1,
        ]);
        $this->assertCount(2, $byProcess);
    }

    /**
     * Tests that no duplicate role assignment can be made
     */
    public function testObjectRoleUnique(): void
    {
        /** @var $user User */
        $user = $this->entityManager->getRepository(User::class)
            ->find(TestFixtures::PROCESS_OWNER['id']);

        $role = new UserObjectRole();
        $role->setRole(UserObjectRole::ROLE_PROCESS_OWNER);
        $role->setObjectType(Process::class);
        $role->setObjectId(1);
        $user->addObjectRole($role);

        $this->entityManager->persist($role);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    /**
     * Tests that objectRoles are deleted when the user is deleted
     *
     * @group now
     */
    public function testDeletingUserDeletesRoles(): void
    {
        /** @var $user User */
        $user = $this->entityManager->getRepository(User::class)
            ->find(TestFixtures::PROCESS_OWNER['id']);

        $before = $this->getObjectRoleRepository()->findBy([
            'objectType' => Process::class,
            'objectId'   => 1,
        ]);
        $this->assertCount(1, $before);

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        $after = $this->getObjectRoleRepository()->findBy([
            'objectType' => Process::class,
            'objectId'   => 1,
        ]);

        $this->assertCount(0, $after);
    }
}
