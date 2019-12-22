<?php
declare(strict_types=1);

namespace App\Tests\Entity;

use App\DataFixtures\TestFixtures;
use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\User;
use App\PHPUnit\RefreshDatabaseTrait;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @group UserEntity
 */
class UserTest extends KernelTestCase
{
    use RefreshDatabaseTrait;

    private const invalidNames = [
        '123', // digits
        'TEst1', // digit
        'Test@de', // @ not allowed
        'Test,de', // , not allowed
        'Test?de', // ? not allowed
        'Test!de', // ! not allowed
        'Test"de', // " not allowed
        'Test§de', // § not allowed
        'Test$de', // $ not allowed
        'Test%de', // % not allowed
        'Test&de', // & not allowed
        "Test\de", // \ not allowed
        'Test/de', // / not allowed
        'Test(de', // ( not allowed
        'Test)de', // ) not allowed
        'Test<de', // < not allowed
        'Test>de', // > not allowed
        'Test[de', // [ not allowed
        'Test]de', // ] not allowed
        'Test{de', // { not allowed
        'Test}de', // } not allowed
        'Test#de', // # not allowed
        'Test:de', // : not allowed
        'Test;de', // ; not allowed
        'Test=de', // = not allowed
        'Test+de', // + not allowed
        'Test~de', // ~ not allowed
    ];

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $entityManager;

    /**
     * Tests the repository functions, checking for soft-deleted users.
     */
    public function testFindUsers(): void
    {
        /* @var $found User[] */
        $all = $this->getUserRepository()
            ->findAll();

        $this->assertCount(6, $all);

        $adminIdentity = $this->getUserRepository()
            ->findNonDeleted(TestFixtures::ADMIN['id']);
        $this->assertInstanceOf(User::class, $adminIdentity);

        $deletedIdentity = $this->getUserRepository()
            ->findNonDeleted(TestFixtures::DELETED_USER['id']);
        $this->assertNull($deletedIdentity);

        $adminCriteria = $this->getUserRepository()
            ->findOneNonDeletedBy(['email' => TestFixtures::ADMIN['email']]);
        $this->assertInstanceOf(User::class, $adminCriteria);

        // findOneBy returns the deleted record, findOneNonDeletedBy does not
        $deleted = $this->getUserRepository()
            ->findOneBy(['email' => TestFixtures::DELETED_USER['email']]);
        $this->assertInstanceOf(User::class, $deleted);
        $deletedCriteria = $this->getUserRepository()
            ->findOneNonDeletedBy(['email' => TestFixtures::DELETED_USER['email']]);
        $this->assertNull($deletedCriteria);

        // finds only the undeleted users
        $nonDeleted = $this->getUserRepository()
            ->findNonDeletedBy(['isActive' => true]);
        $this->assertCount(5, $nonDeleted);
        $this->assertSame(1, $nonDeleted[0]->getId());
    }

    protected function getUserRepository(): UserRepository
    {
        return $this->entityManager->getRepository(User::class);
    }

    /**
     * Tests the defaults for new users.
     */
    public function testCreateAndReadUser(): void
    {
        $user = new User();
        $user->setEmail('test@example.net');
        $user->setUsername('tester');
        $user->setPassword('no-secret');
        $user->setRoles(['ROLE_ADMIN']);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->entityManager->clear();

        /* @var $found User */
        $found = $this->getUserRepository()
            ->findOneBy(['email' => 'test@example.net']);

        $this->assertSame('tester', $found->getUsername());
        $this->assertSame('no-secret', $found->getPassword());
        $this->assertContains('ROLE_ADMIN', $found->getRoles());

        // added by default
        $this->assertContains('ROLE_USER', $found->getRoles());

        $this->assertSame(true, $user->isActive());
        $this->assertSame(false, $user->isValidated());
        $this->assertCount(0, $user->getObjectRoles());
        $this->assertCount(0, $user->getValidations());
        $this->assertSame(null, $user->getDeletedAt());
        $this->assertSame(false, $user->isDeleted());
        $this->assertNull($user->getFirstName());
        $this->assertNull($user->getLastName());

        // timestampable listener works
        $this->assertInstanceOf(\DateTimeImmutable::class,
            $user->getCreatedAt());

        // ID 1 - 6 are created by the fixtures
        $this->assertSame(7, $user->getId());
    }

    public function testUpdateUser()
    {
        $user = $this->getUserRepository()->find(TestFixtures::ADMIN['id']);
        $this->assertSame(TestFixtures::ADMIN['username'], $user->getUsername());

        $user->setIsValidated(true);
        $user->setIsActive(false);
        $user->setFirstName('Peter');
        $user->setLastName('Pan');
        $user->setRoles([User::ROLE_ADMIN, User::ROLE_PROCESS_OWNER]);
        $user->setEmail('new@zukunftsstadt.de');

        $this->entityManager->flush();
        $this->entityManager->clear();

        $updated = $this->getUserRepository()->find(TestFixtures::ADMIN['id']);
        $this->assertSame('new@zukunftsstadt.de', $updated->getEmail());
        $this->assertTrue($updated->isValidated());
        $this->assertFalse($updated->isActive());
        $this->assertSame(
            [User::ROLE_ADMIN, User::ROLE_PROCESS_OWNER, User::ROLE_USER],
            $updated->getRoles()
        );
        $this->assertSame('Peter', $updated->getFirstName());
        $this->assertSame('Pan', $updated->getLastName());
    }

    /**
     * Tests marking a User as deleted.
     */
    public function testSoftdeleteUser(): void
    {
        /* @var $admin User */
        $admin = $this->getUserRepository()
            ->findNonDeleted(1);

        $this->assertSame(null, $admin->getDeletedAt());
        $admin->markDeleted();

        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->assertInstanceOf(\DateTimeImmutable::class,
            $admin->getDeletedAt());
        $this->assertSame(true, $admin->isDeleted());

        $notFound = $this->getUserRepository()
            ->findNonDeleted(1);
        $this->assertNull($notFound);
    }

    /**
     * Tests that no duplicate emails can be created
     */
    public function testEmailIsUnique(): void
    {
        $user = new User();
        $user->setUsername('tester');
        $user->setEmail(TestFixtures::ADMIN['email']);
        $user->setPassword('no-secret');
        $user->setRoles(['ROLE_ADMIN']);
        $this->entityManager->persist($user);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    /**
     * Tests that no duplicate usernames can be created
     */
    public function testUsernameIsUnique(): void
    {
        $user = new User();
        $user->setUsername(TestFixtures::ADMIN['username']);
        $user->setEmail('test@zukunftsstadt.de');
        $user->setPassword('no-secret');
        $user->setRoles(['ROLE_ADMIN']);
        $this->entityManager->persist($user);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    public function testRelationsAccessible()
    {
        /* @var $user User */
        $user = $this->getUserRepository()
            ->find(TestFixtures::PROJECT_OWNER['id']);

        $this->assertCount(2, $user->getProjectMemberships());
        $this->assertInstanceOf(ProjectMembership::class, $user->getProjectMemberships()[0]);

        $this->assertCount(2, $user->getCreatedProjects());
        $this->assertInstanceOf(Project::class, $user->getCreatedProjects()[0]);

        // @todo
        $this->assertCount(0, $user->getValidations());
    }

    public function testUsernameRestrictions()
    {
        // fetch a valid user and automatically initialize the service container
        $user = $this->getUserRepository()->find(TestFixtures::ADMIN['id']);

        $validator = self::$container->get(ValidatorInterface::class);

        $invalidUsernames = [
            '123', // no letters
            'a123', // only one letter
            '0test', // not starting with a letter
            'Asd@de', // @ not allowed
            'Test,de', // , not allowed
            'Test-dé', // only a-ZA-Z letters
            'Te as', // no spaces
        ];

        foreach ($invalidUsernames as $invalidName) {
            $user->setUsername($invalidName);
            $failing = $validator->validate($user);
            $this->assertSame('Username is not valid.',
                $failing->offsetGet(0)->getMessage(), sprintf(
                    '"%s" should be invalid but is is not', $invalidName));
        }

        $user->setUsername('T-est.DE_de2');
        $valid = $validator->validate($user);
        $this->assertCount(0, $valid);
    }

    public function testFirstNameRestrictions()
    {
        // fetch a valid user and automatically initialize the service container
        $user = $this->getUserRepository()->find(TestFixtures::ADMIN['id']);

        $validator = self::$container->get(ValidatorInterface::class);

        foreach (self::invalidNames as $invalidName) {
            $user->setFirstName($invalidName);
            $failing = $validator->validate($user);
            $this->assertSame(
                'The name contains invalid characters.',
                $failing->offsetGet(0)->getMessage(),
                sprintf('"%s" should be invalid but is is not', $invalidName)
            );
        }

        $user->setFirstName('Hans-Peter D´Artagòn');
        $valid = $validator->validate($user);
        $this->assertCount(0, $valid);
    }

    public function testLastNameRestrictions()
    {
        $pattern = '/[\d\\\\\/,;:_~@?!$%&§=#+"()<>[\]{}]/u';
        $tests = ['Te\st', 'Te\\st', "Te\st", "Te\\st"];
        $res = [];
        foreach ($tests as $test) {
            $res[] = preg_match($pattern, $test);
        }

        // fetch a valid user and automatically initialize the service container
        $user = $this->getUserRepository()->find(TestFixtures::ADMIN['id']);

        $validator = self::$container->get(ValidatorInterface::class);

        foreach (self::invalidNames as $invalidName) {
            $user->setLastName($invalidName);
            $failing = $validator->validate($user);
            $this->assertCount(1, $failing);
            $this->assertSame(
                'The name contains invalid characters.',
                $failing->offsetGet(0)->getMessage(),
                sprintf('"%s" should be invalid but is is not', $invalidName)
            );
        }

        $user->setLastName('Hans-Peter D´Artagòn');
        $valid = $validator->validate($user);
        $this->assertCount(0, $valid);
    }

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
    }
}
