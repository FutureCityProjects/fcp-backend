<?php
declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Fund;
use App\Entity\FundApplication;
use App\Entity\FundConcretization;
use App\Entity\JuryCriterion;
use App\Entity\Process;
use App\Entity\UserObjectRole;
use App\PHPUnit\RefreshDatabaseTrait;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @group FundEntity
 */
class FundTest extends KernelTestCase
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

    protected function getFundRepository(): EntityRepository
    {
        return $this->entityManager->getRepository(Fund::class);
    }

    /**
     * Tests the defaults for new fund.
     */
    public function testCreateAndReadFund(): void
    {
        $before = $this->getFundRepository()
            ->findAll();
        $this->assertCount(2, $before);

        $process = $this->entityManager->getRepository(Process::class)
            ->find(1);

        $fund = new Fund();
        $fund->setName('Testing Fund');
        $fund->setDescription('Just a test');
        $fund->setRegion('world-wide');
        $fund->setImprint('The Testers');
        $fund->setSponsor('Dresden');
        $fund->setProcess($process);

        $this->entityManager->persist($fund);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $after = $this->getFundRepository()
            ->findAll();
        $this->assertCount(3, $after);

        /* @var $found Fund */
        $found = $this->getFundRepository()
            ->findOneBy(['name' => 'Testing Fund']);

        $this->assertSame('Just a test', $found->getDescription());
        $this->assertSame('testing-fund', $found->getSlug());

        // defaults
        $this->assertSame(Fund::STATE_INACTIVE, $found->getState());
        $this->assertSame(2, $found->getJurorsPerApplication());
        $this->assertNull($found->getCriteria());
        $this->assertNull($found->getLogo());
        $this->assertInstanceOf(Process::class, $fund->getProcess());
        $this->assertNull($found->getSubmissionBegin());
        $this->assertNull($found->getSubmissionEnd());
        $this->assertNull($found->getRatingBegin());
        $this->assertNull($found->getRatingEnd());
        $this->assertNull($found->getBriefingDate());
        $this->assertNull($found->getFinalJuryDate());
        $this->assertCount(0, $found->getApplications());
        $this->assertCount(0, $found->getJuryCriteria());

        // ID 1+2 are created by the fixtures
        $this->assertSame(3, $found->getId());
    }

    /**
     * Tests that no duplicate names can be created
     */
    public function testNameIsUnique(): void
    {
        $process = $this->entityManager->getRepository(Process::class)
            ->find(1);

        $fund = new Fund();
        $fund->setName('Future City');
        $fund->setDescription('Just a test');
        $fund->setRegion('world-wide');
        $fund->setImprint('The Testers');
        $fund->setSponsor('Dresden');
        $fund->setProcess($process);

        $this->entityManager->persist($fund);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    public function testSlugIsUpdatedAutomatically(): void
    {
        /* @var $fund Fund */
        $fund = $this->getFundRepository()->find(1);

        $this->assertSame('future-city', $fund->getSlug());

        $fund->setName('A name with ümläutß');
        $this->entityManager->flush();

        /* @var $after Process */
        $after = $this->getFundRepository()->find(1);
        $this->assertSame('a-name-with-umlautss', $after->getSlug());
    }

    public function testRelationsAccessible()
    {
        /* @var $fund Fund */
        $fund = $this->getFundRepository()->find(1);

        $this->assertInstanceOf(Process::class, $fund->getProcess());

        $this->assertCount(1, $fund->getApplications());
        $this->assertInstanceOf(FundApplication::class, $fund->getApplications()[0]);

        $this->assertCount(1, $fund->getConcretizations());
        $this->assertInstanceOf(FundConcretization::class, $fund->getConcretizations()[0]);

        $this->assertCount(1, $fund->getJuryCriteria());
        $this->assertInstanceOf(JuryCriterion::class, $fund->getJuryCriteria()[0]);
    }

    public function testDeleteRemovesObjectRoles()
    {
        $this->assertSame(1,
            $this->entityManager->getRepository(UserObjectRole::class)
                ->count(['objectId' => 1, 'objectType' => Fund::class]));

        /** @var Fund $fund */
        $fund = $this->getFundRepository()->find(1);
        $this->entityManager->remove($fund);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $notFound = $this->getFundRepository()->find(1);
        $this->assertNull($notFound);
        $this->assertSame(0,
            $this->entityManager->getRepository(UserObjectRole::class)
                ->count(['objectId' => 1, 'objectType' => Fund::class]));
    }
}
