<?php
declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Fund;
use App\Entity\FundConcretization;
use App\PHPUnit\RefreshDatabaseTrait;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class FundConcretizationTest extends KernelTestCase
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

    protected function getConcretizationRepository(): EntityRepository
    {
        return $this->entityManager->getRepository(FundConcretization::class);
    }

    public function testCreateAndReadConcretization(): void
    {
        $before = $this->getConcretizationRepository()
            ->findAll();
        $this->assertCount(2, $before);

        /** @var $fund Fund */
        $fund = $this->entityManager->getRepository(Fund::class)
            ->find(1);

        $concretization = new FundConcretization();
        $concretization->setQuestion('Is it sustainable?');
        $concretization->setDescription('Sustainability');
        $concretization->setMaxLength(44);
        $fund->addConcretization($concretization);

        $this->entityManager->persist($concretization);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $after = $this->getConcretizationRepository()->findAll();
        $this->assertCount(3, $after);

        /* @var $found FundConcretization */
        $found = $this->getConcretizationRepository()
            ->findOneBy(['question' => 'Is it sustainable?']);

        $this->assertSame('Sustainability', $found->getDescription());
        $this->assertSame(44, $found->getMaxLength());
        $this->assertSame($fund->getId(), $found->getFund()->getId());

        // ID 1+2 are created by the fixtures
        $this->assertSame(3, $found->getId());
    }

    /**
     * Tests that no duplicate names can be created
     */
    public function testQuestionIsUnique(): void
    {
        /** @var $fund Fund */
        $fund = $this->entityManager->getRepository(Fund::class)
            ->find(1);

        $concretization = new FundConcretization();
        $concretization->setQuestion('How does it help?');
        $concretization->setDescription('irrelevant');
        $fund->addConcretization($concretization);

        $this->entityManager->persist($concretization);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }
}
