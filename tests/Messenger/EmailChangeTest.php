<?php
declare(strict_types=1);

namespace App\Tests\Messenger;

use App\DataFixtures\TestFixtures;
use App\Entity\User;
use App\Entity\Validation;
use App\Message\UserEmailChangeMessage;
use App\MessageHandler\UserEmailChangeMessageHandler;
use App\PHPUnit\RefreshDatabaseTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class EmailChangeTest extends KernelTestCase
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
        $msg = new UserEmailChangeMessage(
            TestFixtures::PROCESS_OWNER['id'],
            'new@zukunftsstadt.de',
            'https://fcp.vrok.de/confirm-validation/?id={{id}}&token={{token}}&type={{type}}'
        );

        $po = $this->entityManager->getRepository(User::class)
            ->find(TestFixtures::PROCESS_OWNER['id']);
        $notFound = $this->entityManager->getRepository(Validation::class)
            ->findOneBy(['user' => $po]);
        $this->assertNull($notFound);

        $handler = self::$container->get(UserEmailChangeMessageHandler::class);
        $handler($msg);

        // check for sent emails, @see Symfony\Component\Mailer\Test\Constraint\EmailCount
        // & Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait, we don't
        // use the trait as it requires the usage of a WebTestCase
        $logger = self::$container->get('mailer.logger_message_listener');
        $sent = array_filter($logger->getEvents()->getEvents(), function($e) {
            return !$e->isQueued();
        });
        $this->assertCount(1, $sent);

        $validation = $this->entityManager->getRepository(Validation::class)
            ->findOneBy(['user' => $po]);
        $this->assertInstanceOf(Validation::class, $validation);
        $this->assertSame(Validation::TYPE_CHANGE_EMAIL, $validation->getType());
        $this->assertSame(['email' => 'new@zukunftsstadt.de'], $validation->getContent());
    }
}
