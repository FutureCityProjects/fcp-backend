<?php
declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Fund;
use App\Entity\JuryCriterion;
use App\PHPUnit\RefreshDatabaseTrait;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class JuryCriterionTest extends KernelTestCase
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

    protected function getCriteriaRepository(): EntityRepository
    {
        return $this->entityManager->getRepository(JuryCriterion::class);
    }

    public function testCreateAndReadCriterion(): void
    {
        $before = $this->getCriteriaRepository()
            ->findAll();
        $this->assertCount(1, $before);

        /** @var $fund Fund */
        $fund = $this->entityManager->getRepository(Fund::class)
            ->find(1);

        $criterion = new JuryCriterion();
        $criterion->setName('Sustainability');
        $criterion->setQuestion('Is it sustainable?');
        $fund->addJuryCriterion($criterion);

        $this->entityManager->persist($criterion);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $after = $this->getCriteriaRepository()->findAll();
        $this->assertCount(2, $after);

        /* @var $found JuryCriterion */
        $found = $this->getCriteriaRepository()
            ->findOneBy(['name' => 'Sustainability']);

        $this->assertSame('Is it sustainable?', $found->getQuestion());
        $this->assertSame($fund->getId(), $found->getFund()->getId());

        // ID 1 is created by the fixtures
        $this->assertSame(2, $found->getId());
    }

    /**
     * Tests that no duplicate names can be created
     */
    public function testNameIsUnique(): void
    {
        /** @var $fund Fund */
        $fund = $this->entityManager->getRepository(Fund::class)
            ->find(1);

        $criterion = new JuryCriterion();
        $criterion->setName('Realistic expectations');
        $criterion->setQuestion('Will it fail?');
        $fund->addJuryCriterion($criterion);

        $this->entityManager->persist($criterion);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }
}
