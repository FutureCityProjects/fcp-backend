<?php
declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\User;
use App\Entity\Validation;
use App\PHPUnit\RefreshDatabaseTrait;
use DateTimeImmutable;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @group ValidationEntity
 */
class ValidationTest extends KernelTestCase
{
    use RefreshDatabaseTrait;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $entityManager;

    /**
     * Tests the defaults for new validations.
     */
    public function testCreateAndReadValidation(): void
    {
        /** @var $user User */
        $user = $this->entityManager->getRepository(User::class)
            ->find(1);

        $validation = new Validation();
        $validation->setType(Validation::TYPE_CHANGE_EMAIL);
        $validation->setContent(['new_email' => 'new@zukunftsstadt.de']);
        $validation->setExpiresAt(new DateTimeImmutable('2099-01-01'));

        $validation->generateToken();
        $this->assertNotEmpty($validation->getToken());

        $user->addValidation($validation);
        $this->assertEquals($user, $validation->getUser());

        $this->entityManager->persist($validation);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->assertSame(1, $validation->getId());

        /** @var $found Validation **/
        $found = $this->getValidationRepository()
            ->find(1);

        $this->assertInstanceOf(Validation::class, $found);
        $this->assertFalse($found->isExpired());
        $this->assertInstanceOf(User::class, $found->getUser());

        // timestampable listener works
        $this->assertInstanceOf(\DateTimeImmutable::class,
            $found->getCreatedAt());

        $found->setExpiresAt(new DateTimeImmutable('2000-01-01'));
        $this->assertTrue($found->isExpired());
    }

    protected function getValidationRepository(): EntityRepository
    {
        return $this->entityManager->getRepository(Validation::class);
    }

    /**
     * Tests that pending validations are deleted when the user is deleted
     */
    public function testDeletingUserDeletesValidation(): void
    {
        /** @var $user User */
        $user = $this->entityManager->getRepository(User::class)
            ->find(1);

        $validation = new Validation();
        $validation->setType(Validation::TYPE_CHANGE_EMAIL);
        $validation->setExpiresAt(new DateTimeImmutable());
        $validation->generateToken();

        $user->addValidation($validation);

        $this->entityManager->persist($validation);
        $this->entityManager->flush();

        $all = $this->getValidationRepository()->findAll();
        $this->assertCount(1, $all);

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        $none = $this->getValidationRepository()->findAll();
        $this->assertCount(0, $none);
    }

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
    }
}
