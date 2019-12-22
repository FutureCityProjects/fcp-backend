<?php
declare(strict_types=1);

namespace App\Tests\Entity;

use App\DataFixtures\TestFixtures;
use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\User;
use App\PHPUnit\RefreshDatabaseTrait;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @group ProjectMembershipEntity
 */
class ProjectMembershipTest extends KernelTestCase
{
    use RefreshDatabaseTrait;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $entityManager;

    /**
     * Tests the defaults for new roles.
     */
    public function testCreateAndReadMember(): void
    {
        /** @var $user User */
        $user = $this->entityManager->getRepository(User::class)
            ->find(TestFixtures::PROCESS_OWNER['id']);

        /** @var $project Project */
        $project = $this->entityManager->getRepository(Project::class)
            ->find(2);

        $before = $this->getMembershipRepository()->findBy(['user' => $user]);
        $this->assertCount(0, $before);

        $membership = new ProjectMembership();
        $membership->setRole(ProjectMembership::ROLE_MEMBER);
        $membership->setMotivation('po motivation');
        $membership->setSkills('po skills');
        $membership->setTasks('po tasks');
        $project->addMembership($membership);
        $user->addProjectMembership($membership);

        $this->entityManager->persist($membership);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $byUser = $this->getMembershipRepository()->findBy(['user' => $user]);
        $this->assertCount(1, $byUser);

        $this->assertSame('po motivation', $byUser[0]->getMotivation());
        $this->assertSame('po skills', $byUser[0]->getSkills());
        $this->assertSame('po tasks', $byUser[0]->getTasks());

        $byProject = $this->getMembershipRepository()->findBy([
            'project' => $project,
        ]);
        $this->assertCount(3, $byProject);
    }

    protected function getMembershipRepository(): EntityRepository
    {
        return $this->entityManager->getRepository(ProjectMembership::class);
    }

    /**
     * Tests that no duplicate memberships can be assigned
     */
    public function testMembershipUnique(): void
    {
        /** @var $user User */
        $user = $this->entityManager->getRepository(User::class)
            ->find(TestFixtures::PROJECT_OWNER['id']);

        /** @var $project Project */
        $project = $this->entityManager->getRepository(Project::class)
            ->find(2);

        $membership = new ProjectMembership();
        $membership->setRole(ProjectMembership::ROLE_MEMBER);
        $project->addMembership($membership);
        $user->addProjectMembership($membership);

        $this->entityManager->persist($membership);

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
            ->find(TestFixtures::PROJECT_OWNER['id']);

        $before = $this->getMembershipRepository()->findBy([
            'user' => $user,
        ]);
        $this->assertCount(2, $before);

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        $after = $this->getMembershipRepository()->findBy([
            'user' => $user,
        ]);

        $this->assertCount(0, $after);
    }

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
    }
}
