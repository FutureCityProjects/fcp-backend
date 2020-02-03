<?php
declare(strict_types=1);

namespace App\Tests\Entity\Helper;

use App\DataFixtures\TestFixtures;
use App\Entity\FundApplication;
use App\Entity\Helper\ProjectHelper;
use App\Entity\Project;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @group ProjectHelper
 */
class ProjectHelperTest extends KernelTestCase
{
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

    public function testIsPlanAvailable(): void
    {
        /** @var Project $project */
        $project = $this->getProjectRepository()
            ->find(TestFixtures::PROJECT['id']);
        $project->setVision(null);

        $helper = new ProjectHelper($project);

        $this->assertFalse($helper->isPlanAvailable());

        $project->setProfileSelfAssessment(Project::SELF_ASSESSMENT_100_PERCENT);
        $this->assertFalse($helper->isPlanAvailable());

        $project->setVision('vision');
        $this->assertTrue($helper->isPlanAvailable());

        $project->setProfileSelfAssessment(Project::SELF_ASSESSMENT_0_PERCENT);
        $this->assertFalse($helper->isPlanAvailable());

        $project->setProfileSelfAssessment(Project::SELF_ASSESSMENT_100_PERCENT);
        $project->setState(Project::STATE_DEACTIVATED);
        $this->assertFalse($helper->isPlanAvailable());
    }

    public function testIsApplicationAvailable(): void
    {
        /** @var Project $project */
        $project = $this->getProjectRepository()
            ->find(TestFixtures::PROJECT['id']);

        $helper = new ProjectHelper($project);
        $this->assertFalse($helper->isApplicationAvailable());

        $project->setPlanSelfAssessment(Project::SELF_ASSESSMENT_100_PERCENT);
        $this->assertFalse($helper->isApplicationAvailable());

        $application = $project->getApplications()[0];
        $application->setState(FundApplication::STATE_DETAILING);
        $this->assertTrue($helper->isApplicationAvailable());

        $project->setState(Project::STATE_DEACTIVATED);
        $this->assertFalse($helper->isPlanAvailable());

        $project->setState(Project::STATE_ACTIVE);
        $project->removeApplication($application);
        $this->assertFalse($helper->isApplicationAvailable());
    }

    public function testIsSubmissionAvailable(): void
    {
        /** @var Project $project */
        $project = $this->getProjectRepository()
            ->find(TestFixtures::PROJECT['id']);

        $helper = new ProjectHelper($project);

        // @todo ausdefinieren

        $this->assertFalse($helper->isSubmissionAvailable());
    }

    public function testIsApplicationSubmitted(): void
    {
        /** @var Project $project */
        $project = $this->getProjectRepository()
            ->find(TestFixtures::PROJECT['id']);
        $application = $project->getApplications()[0];

        $helper = new ProjectHelper($project);

        $this->assertFalse($helper->isApplicationSubmitted());

        $application->setState(FundApplication::STATE_SUBMITTED);
        $this->assertTrue($helper->isApplicationSubmitted());
    }

    public function testHasDuplicateTaskIDs(): void
    {
        /** @var Project $project */
        $project = $this->getProjectRepository()
            ->find(TestFixtures::PROJECT['id']);

        $helper = new ProjectHelper($project);

        // no tasks, no duplicates...
        $this->assertFalse($helper->hasDuplicateTaskIDs());

        $project->setTasks([
           ['description' => 'abc', 'id' => '1'],
           ['description' => 'abc', 'id' => '2'],
        ]);
        $this->assertFalse($helper->hasDuplicateTaskIDs());

        $project->setTasks([
            ['description' => 'abc', 'id' => '1'],
            ['description' => 'abc', 'id' => '1'],
        ]);
        $this->assertTrue($helper->hasDuplicateTaskIDs());
    }

    public function testgetMaxMonthFromTasks(): void
    {
        /** @var Project $project */
        $project = $this->getProjectRepository()
            ->find(TestFixtures::PROJECT['id']);

        $helper = new ProjectHelper($project);

        // no tasks, no months...
        $this->assertSame(0, $helper->getMaxMonthFromTasks());

        $project->setTasks([
            ['description' => 'abc', 'id' => '1', 'months' => [1,2,3]],
            ['description' => 'abc', 'id' => '2', 'months' => [1,5,3]],
        ]);
        $this->assertSame(5, $helper->getMaxMonthFromTasks());
    }

    public function testHasDuplicatePackageIDs(): void
    {
        /** @var Project $project */
        $project = $this->getProjectRepository()
            ->find(TestFixtures::PROJECT['id']);

        $helper = new ProjectHelper($project);

        // no packages, no duplicates...
        $this->assertFalse($helper->hasDuplicatePackageIDs());

        $project->setWorkPackages([
            ['name' => 'abc', 'id' => '1'],
            ['name' => 'def', 'id' => '2'],
        ]);
        $this->assertFalse($helper->hasDuplicatePackageIDs());

        $project->setWorkPackages([
            ['name' => 'abc', 'id' => '1'],
            ['name' => 'def', 'id' => '1'],
        ]);
        $this->assertTrue($helper->hasDuplicatePackageIDs());
    }

    public function testHasDuplicatePackageNames(): void
    {
        /** @var Project $project */
        $project = $this->getProjectRepository()
            ->find(TestFixtures::PROJECT['id']);

        $helper = new ProjectHelper($project);

        // no packages, no duplicates...
        $this->assertFalse($helper->hasDuplicatePackageNames());

        $project->setWorkPackages([
            ['name' => 'abc', 'id' => '1'],
            ['name' => 'def', 'id' => '2'],
        ]);
        $this->assertFalse($helper->hasDuplicatePackageNames());

        $project->setWorkPackages([
            ['name' => 'def', 'id' => '1'],
            ['name' => 'def', 'id' => '2'],
        ]);
        $this->assertTrue($helper->hasDuplicatePackageNames());
    }
}
