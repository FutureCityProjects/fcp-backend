<?php
declare(strict_types=1);

namespace App\Tests\Messenger;

use App\DataFixtures\TestFixtures;
use App\Entity\User;
use App\Entity\Validation;
use App\Message\UserRegisteredMessage;
use App\MessageHandler\UserRegisteredMessageHandler;
use App\PHPUnit\RefreshDatabaseTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UserRegisteredTest extends KernelTestCase
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

    public function testHandlerSendsMessage()
    {
        $admin = $this->entityManager->getRepository(User::class)
            ->find(TestFixtures::PROJECT_MEMBER['id']);
        $admin->setIsValidated(false);
        $this->entityManager->flush();

        $msg = new UserRegisteredMessage(
            TestFixtures::PROJECT_MEMBER['id'],
            'https://fcp.vrok.de/confirm-validation/?id={{id}}&token={{token}}&type={{type}}'
        );

        $handler = self::$container->get(UserRegisteredMessageHandler::class);

        $this->assertCount(0,
            $this->entityManager->getRepository(Validation::class)
                ->findAll()
        );

        $handler($msg);

        // check for sent emails, @see Symfony\Component\Mailer\Test\Constraint\EmailCount
        // & Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait, we don't
        // use the trait as it requires the usage of a WebTestCase
        $logger = self::$container->get('mailer.logger_message_listener');
        $sent = array_filter($logger->getEvents()->getEvents(), function($e) {
            return !$e->isQueued();
        });
        $this->assertCount(1, $sent);

        $this->assertCount(1,
            $this->entityManager->getRepository(Validation::class)
                ->findAll()
        );
    }
}
