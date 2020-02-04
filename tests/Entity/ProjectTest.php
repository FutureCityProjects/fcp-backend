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

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
    }

    protected function getProjectRepository(): EntityRepository
    {
        return $this->entityManager->getRepository(Project::class);
    }

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

        // plan data
        $project->setPlanSelfAssessment(Project::SELF_ASSESSMENT_50_PERCENT);
        $project->setTasks([
            ['id' => '123456', 'description' => 'text']
        ]);
        $project->setWorkPackages([
            ['id' => '123456', 'description' => 'text', 'name' => 'AP1']
        ]);
        $project->setOutcome(['outcome1', 'outcome2']);
        $project->setImpact(['impact1', 'impact2']);
        $project->setResults(['result1', 'result2']);
        $project->setTargetGroups(['group1', 'group2']);
        $project->setUtilization('utilization');

        // application data
        $project->setContactEmail('contact@zukunftsstadt.de');
        $project->setContactName('Projektträger');
        $project->setContactPhone('01234-12345667');
        $project->setHolderAddressInfo('im Hinterhaus');
        $project->setHolderStreet('Waldweg 1');
        $project->setHolderCity('Dresden');
        $project->setHolderZipCode('01234');

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

        // plan data
        $this->assertSame(Project::SELF_ASSESSMENT_50_PERCENT, $project->getPlanSelfAssessment());
        $this->assertSame([
            ['id' => '123456', 'description' => 'text']
        ], $project->getTasks());
        $this->assertSame([
            ['id' => '123456', 'description' => 'text', 'name' => 'AP1']
        ], $project->getWorkPackages());
        $this->assertSame(['outcome1', 'outcome2'], $project->getOutcome());
        $this->assertSame(['impact1', 'impact2'], $project->getImpact());
        $this->assertSame(['result1', 'result2'], $project->getResults());
        $this->assertSame(['group1', 'group2'], $project->getTargetGroups());
        $this->assertSame('utilization', $project->getUtilization());

        // application data
        $this->assertSame('contact@zukunftsstadt.de', $project->getContactEmail());
        $this->assertSame('Projektträger', $project->getContactName());
        $this->assertSame('01234-12345667', $project->getContactPhone());
        $this->assertSame('im Hinterhaus', $project->getHolderAddressInfo());
        $this->assertSame('Waldweg 1', $project->getHolderStreet());
        $this->assertSame('Dresden', $project->getHolderCity());
        $this->assertSame('01234', $project->getHolderZipCode());

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
        $this->assertNull($found->getGoal());
        $this->assertNull($found->getPicture());
        $this->assertNull($found->getVision());
        $this->assertNull($found->getVisualization());
        $this->assertCount(0, $found->getApplications());
        $this->assertCount(0, $found->getMemberships());

        // ID 1-4 is created by the fixtures
        $this->assertSame(5, $found->getId());
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
        $project = $this->getProjectRepository()
            ->find(TestFixtures::PROJECT['id']);

        $this->assertCount(2, $project->getMemberships());
        $this->assertInstanceOf(ProjectMembership::class, $project->getMemberships()[0]);

        $this->assertCount(1, $project->getApplications());
        $this->assertInstanceOf(FundApplication::class, $project->getApplications()[0]);

        $this->assertInstanceOf(Project::class, $project->getInspiration());
        $this->assertInstanceOf(Process::class, $project->getProcess());
        $this->assertInstanceOf(User::class, $project->getCreatedBy());

        /* @var $project Project */
        $idea = $this->getProjectRepository()
            ->find(TestFixtures::IDEA['id']);
        $this->assertCount(2, $idea->getResultingProjects());
        $this->assertInstanceOf(Project::class, $idea->getResultingProjects()[0]);
    }

    // @todo name is unique per process
}
