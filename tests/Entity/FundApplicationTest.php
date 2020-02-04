<?php
declare(strict_types=1);

namespace App\Tests\Entity;

use App\DataFixtures\TestFixtures;
use App\Entity\Fund;
use App\Entity\FundApplication;
use App\Entity\Project;
use App\PHPUnit\RefreshDatabaseTrait;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class FundApplicationTest extends KernelTestCase
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

    protected function getApplicationRepository(): EntityRepository
    {
        return $this->entityManager->getRepository(FundApplication::class);
    }

    public function testCreateAndReadApplication(): void
    {
        $before = $this->getApplicationRepository()
            ->findAll();
        $this->assertCount(1, $before);

        /** @var Fund $fund */
        $fund = $this->entityManager->getRepository(Fund::class)
            ->find(TestFixtures::ACTIVE_FUND['id']);

        /** @var Project $project */
        $project = $this->entityManager->getRepository(Project::class)
            ->find(TestFixtures::LOCKED_PROJECT['id']);

        $application = new FundApplication();
        $application->setConcretizations([1 => 'Is it sustainable?']);
        $application->setConcretizationSelfAssessment(FundApplication::SELF_ASSESSMENT_75_PERCENT);
        $application->setRequestedFunding(50000.0);
        $application->setApplicationSelfAssessment(FundApplication::SELF_ASSESSMENT_25_PERCENT);

        $fund->addApplication($application);
        $project->addApplication($application);

        $this->entityManager->persist($application);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $after = $this->getApplicationRepository()->findAll();
        $this->assertCount(2, $after);

        /* @var $found FundApplication */
        $found = $this->getApplicationRepository()
            ->find(2);

        $this->assertSame([1 => 'Is it sustainable?'], $found->getConcretizations());
        $this->assertSame(FundApplication::SELF_ASSESSMENT_75_PERCENT, $found->getConcretizationSelfAssessment());

        $this->assertSame(50000.0, $found->getRequestedFunding());
        $this->assertSame(FundApplication::SELF_ASSESSMENT_25_PERCENT, $found->getApplicationSelfAssessment());

        $this->assertSame($fund->getId(), $found->getFund()->getId());
        $this->assertSame($project->getId(), $found->getProject()->getId());
    }

    /**
     * Tests that no duplicate applications can be created for one fund and one
     * project
     */
    public function testApplicationIsUniquePerProject(): void
    {
        /** @var $fund Fund */
        $fund = $this->entityManager->getRepository(Fund::class)
            ->find(TestFixtures::ACTIVE_FUND['id']);

        /** @var Project $project */
        $project = $this->entityManager->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);

        $application = new FundApplication();
        $fund->addApplication($application);
        $project->addApplication($application);

        $this->entityManager->persist($application);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }
}
