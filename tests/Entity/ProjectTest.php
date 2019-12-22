<?php
declare(strict_types=1);

namespace App\Tests\Entity;

use App\DataFixtures\TestFixtures;
use App\Entity\FundApplication;
use App\Entity\Process;
use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\User;
use App\PHPUnit\RefreshDatabaseTrait;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @group ProjectEntity
 */
class ProjectTest extends KernelTestCase
{
    use RefreshDatabaseTrait;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $entityManager;

    /**
     * Tests the defaults for new processes.
     */
    public function testCreateAndReadProject(): void
    {
        $before = $this->getProjectRepository()
            ->findAll();
        $this->assertCount(4, $before);

        /** @var $process Process */
        $process = $this->entityManager->getRepository(Process::class)
            ->find(1);

        /** @var $user User */
        $user = $this->entityManager->getRepository(User::class)
            ->find(TestFixtures::PROJECT_MEMBER['id']);

        /* @var $idea Project */
        $idea = $this->getProjectRepository()->find(1);

        $project = new Project();
        $project->setName('Testing Project');
        $project->setDescription('long description');
        $project->setShortDescription('short description');
        $project->setCreatedBy($user);
        $project->setInspiration($idea);
        $project->setProgress(Project::PROGRESS_CREATING_PROFILE);
        $process->addProject($project);

        $this->entityManager->persist($project);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $after = $this->getProjectRepository()->findAll();
        $this->assertCount(5, $after);

        /* @var $found Project */
        $found = $this->getProjectRepository()
            ->findOneBy(['name' => 'Testing Project']);

        $this->assertSame('testing-project', $found->getSlug());
        $this->assertSame('long description', $found->getDescription());
        $this->assertSame('short description', $found->getShortDescription());

        $this->assertSame($user->getId(), $found->getCreatedBy()->getId());
        $this->assertSame($idea->getId(), $found->getInspiration()->getId());

        // timestampable listener works
        $this->assertInstanceOf(\DateTimeImmutable::class,
            $idea->getCreatedAt());

        $this->assertSame(Project::SELF_ASSESSMENT_0_PERCENT,
            $found->getProfileSelfAssessment());
        $this->assertSame(Project::STATE_ACTIVE, $found->getState());
        $this->assertSame(Project::PROGRESS_CREATING_PROFILE,
            $found->getProgress());

        $this->assertFalse($found->isLocked());
        $this->assertNull($found->getChallenges());
        $this->assertNull($found->getDelimitation());
        $this->assertNull($found->getTarget());
        $this->assertNull($found->getPicture());
        $this->assertNull($found->getVision());
        $this->assertNull($found->getVisualization());
        $this->assertCount(0, $found->getApplications());
        $this->assertCount(0, $found->getMemberships());

        // ID 1-4 is created by the fixtures
        $this->assertSame(5, $found->getId());
    }

    protected function getProjectRepository(): EntityRepository
    {
        return $this->entityManager->getRepository(Project::class);
    }

    public function testSlugIsUpdatedAutomatically(): void
    {
        /* @var $project Project */
        $project = $this->getProjectRepository()->find(2);

        $this->assertSame('Car-free Dresden', $project->getName());
        $this->assertSame('car-free-dresden', $project->getSlug());

        $project->setName('A better_name, really!');
        $this->entityManager->flush();
        $this->entityManager->clear();

        /* @var $after Project */
        $after = $this->getProjectRepository()->find(2);
        $this->assertSame('a-better-name-really', $after->getSlug());
    }

    public function testRelationsAccessible()
    {
        /* @var $project Project */
        $project = $this->getProjectRepository()->find(2);

        $this->assertCount(2, $project->getMemberships());
        $this->assertInstanceOf(ProjectMembership::class, $project->getMemberships()[0]);

        $this->assertCount(1, $project->getApplications());
        $this->assertInstanceOf(FundApplication::class, $project->getApplications()[0]);

        $this->assertInstanceOf(Process::class, $project->getProcess());
        $this->assertInstanceOf(User::class, $project->getCreatedBy());
    }

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
    }
}
