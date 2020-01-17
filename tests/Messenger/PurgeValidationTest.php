<?php
declare(strict_types=1);

namespace App\Tests\Messenger;

use App\DataFixtures\TestFixtures;
use App\Entity\Process;
use App\Entity\Project;
use App\Entity\User;
use App\Entity\Validation;
use App\Message\PurgeValidationsMessage;
use App\MessageHandler\PurgeValidationsMessageHandler;
use App\PHPUnit\RefreshDatabaseTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PurgeValidationTest extends KernelTestCase
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

    public function testExpiredAccountValidation()
    {
        /** @var User $before */
        $before = $this->entityManager->getRepository(User::class)
            ->find(TestFixtures::JUROR['id']);
        $before->setIsValidated(false);

        $validation = $before->getValidations()[0];
        $validation->setExpiresAt(new \DateTimeImmutable("yesterday"));

        $process = $this->entityManager->getRepository(Process::class)->find(1);

        $project = new Project();
        $project->setShortDescription("this is deactivated");
        $project->setState(Project::STATE_DEACTIVATED);
        $project->setProgress(Project::PROGRESS_IDEA);
        $project->setProcess($process);
        $project->setCreatedBy($before);
        $this->entityManager->persist($project);

        $this->entityManager->flush();
        $projectId = $project->getId();
        $validationId = $validation->getId();
        $this->entityManager->clear();

        $msg = new PurgeValidationsMessage();

        $handler = self::$container->get(PurgeValidationsMessageHandler::class);
        $handler($msg);

        /** @var User $before */
        $after = $this->entityManager->getRepository(User::class)
            ->find(TestFixtures::JUROR['id']);
        $this->assertTrue($after->isDeleted());

        $noProject = $this->entityManager->getRepository(Project::class)
            ->find($projectId);
        $this->assertNull($noProject);

        $noValidation = $this->entityManager->getRepository(Validation::class)
            ->find($validationId);
        $this->assertNull($noValidation);
    }
}
