<?php
declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Fund;
use App\Entity\Process;
use App\Entity\Project;
use App\Entity\UserObjectRole;
use App\PHPUnit\RefreshDatabaseTrait;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @group ProcessEntity
 */
class ProcessTest extends KernelTestCase
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

    protected function getProcessRepository(): EntityRepository
    {
        return $this->entityManager->getRepository(Process::class);
    }

    /**
     * Tests the defaults for new processes.
     */
    public function testCreateAndReadProcess(): void
    {
        $before = $this->getProcessRepository()
            ->findAll();
        $this->assertCount(1, $before);

        $process = new Process();
        $process->setName('Testing Process');
        $process->setDescription('Just a test');
        $process->setRegion('world-wide');
        $process->setImprint('The Testers');
        $process->setGoals(['nothing special']);

        $this->entityManager->persist($process);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $after = $this->getProcessRepository()
            ->findAll();
        $this->assertCount(2, $after);

        /* @var $found Process */
        $found = $this->getProcessRepository()
            ->findOneBy(['name' => 'Testing Process']);

        $this->assertSame('Just a test', $found->getDescription());
        $this->assertSame('testing-process', $found->getSlug());
        $this->assertContains('nothing special', $found->getGoals());

        $this->assertNull($found->getCriteria());
        $this->assertNull($found->getLogo());
        $this->assertCount(0, $found->getFunds());

        // ID 1 is created by the fixtures
        $this->assertSame(2, $found->getId());
    }

    /**
     * Tests that no duplicate names can be created
     */
    public function testNameIsUnique(): void
    {
        $process = new Process();
        $process->setName('Test-Process äüöß');
        $process->setDescription('Just a test');
        $process->setRegion('world-wide');
        $process->setImprint('The Testers');
        $process->setGoals(['nothing special']);

        $this->entityManager->persist($process);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    public function testSlugIsUpdatedAutomatically(): void
    {
        /* @var $process Process */
        $process = $this->getProcessRepository()->find(1);

        $this->assertSame('test-process-auoss', $process->getSlug());

        $process->setName('A better_name, really!');
        $this->entityManager->flush();

        /* @var $after Process */
        $after = $this->getProcessRepository()->find(1);
        $this->assertSame('a-better-name-really', $after->getSlug());
    }

    public function testRelationsAccessible()
    {
        /* @var $process Process */
        $process = $this->getProcessRepository()->find(1);

        $this->assertCount(1, $process->getFunds());
        $this->assertInstanceOf(Fund::class, $process->getFunds()[0]);

        $this->assertCount(4, $process->getProjects());
        $this->assertInstanceOf(Project::class, $process->getProjects()[0]);
    }

    public function testDeleteRemovesObjectRoles()
    {
        $this->assertSame(1,
            $this->entityManager->getRepository(UserObjectRole::class)
                ->count(['objectId' => 1, 'objectType' => Process::class]));

        /* @var $process Process */
        $process = $this->getProcessRepository()->find(1);
        $this->entityManager->remove($process);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $notFound = $this->getProcessRepository()->find(1);
        $this->assertNull($notFound);
        $this->assertSame(0,
            $this->entityManager->getRepository(UserObjectRole::class)
                ->count(['objectId' => 1, 'objectType' => Process::class]));
    }
}
