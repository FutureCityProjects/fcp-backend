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

    public function testIsProfileComplete(): void
    {
        /** @var Project $project */
        $project = $this->getProjectRepository()
            ->find(TestFixtures::PROJECT['id']);
        $project->setVision(null);

        $helper = new ProjectHelper($project);

        $this->assertFalse($helper->isProfileComplete());

        $project->setProfileSelfAssessment(Project::SELF_ASSESSMENT_100_PERCENT);
        $this->assertFalse($helper->isProfileComplete());

        $project->setVision('vision');
        $this->assertTrue($helper->isProfileComplete());

        $project->setProfileSelfAssessment(Project::SELF_ASSESSMENT_0_PERCENT);
        $this->assertFalse($helper->isProfileComplete());
    }

    public function testIsPlanComplete(): void
    {
        /** @var Project $project */
        $project = $this->getProjectRepository()
            ->find(TestFixtures::PROJECT['id']);

        $helper = new ProjectHelper($project);
        $this->assertFalse($helper->isPlanComplete());

        $project->setPlanSelfAssessment(Project::SELF_ASSESSMENT_100_PERCENT);
        $this->assertFalse($helper->isPlanComplete());

        $project->setUtilization("text");
        $this->assertFalse($helper->isPlanComplete());

        $project->setImpact([['text']]);
        $this->assertFalse($helper->isPlanComplete());

        $project->setOutcome([['text']]);
        $this->assertFalse($helper->isPlanComplete());

        $project->setResults([['text']]);
        $this->assertFalse($helper->isPlanComplete());

        $project->setTargetGroups([['text']]);
        $this->assertFalse($helper->isPlanComplete());

        $project->setTasks([['description' => 'text', 'id' => '1']]);
        $this->assertTrue($helper->isPlanComplete());

        // an empty workPackage is not allowed, also a task without a package
        $project->setWorkPackages([['name' => 'text', 'id' => '1']]);
        $this->assertFalse($helper->isPlanComplete());

        $project->setTasks([
            ['description' => 'text', 'id' => '1', 'workPackage' => '1']
        ]);
        $this->assertTrue($helper->isPlanComplete());

        $project->setTasks([
            ['description' => 'text', 'id' => '1', 'workPackage' => '1'],
            ['description' => 'text', 'id' => '2']
        ]);
        $this->assertFalse($helper->isPlanComplete());

        // @todo test ressources
    }

    public function testIsApplicationComplete(): void
    {
        /** @var Project $project */
        $project = $this->getProjectRepository()
            ->find(TestFixtures::PROJECT['id']);
        $application = $project->getApplications()[0];

        $helper = new ProjectHelper($project);

        $this->assertFalse($helper->isApplicationComplete());

        $application->setConcretizationSelfAssessment(FundApplication::SELF_ASSESSMENT_100_PERCENT);
        $this->assertFalse($helper->isApplicationComplete());

        $application->setConcretizations([99 => ['text']]);
        $this->assertFalse($helper->isApplicationComplete());

        $application->setConcretizations([1 => ['text']]);
        $this->assertTrue($helper->isApplicationComplete());

        $application->setConcretizations(null);
        $application->setState(FundApplication::STATE_SUBMITTED);
        $this->assertTrue($helper->isApplicationComplete());

        $project->removeApplication($application);
        $this->assertFalse($helper->isApplicationComplete());
    }

    public function testIsApplicationSubmitted(): void
    {
        /** @var Project $project */
        $project = $this->getProjectRepository()
            ->find(TestFixtures::PROJECT['id']);
        $application = $project->getApplications()[0];

        $helper = new ProjectHelper($project);

        $this->assertFalse($helper->isApplicationComplete());

        $application->setState(FundApplication::STATE_SUBMITTED);
        $this->assertTrue($helper->isApplicationComplete());
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
