<?php
declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\User;
use App\Entity\Validation;
use App\Message\UserRegisteredMessage;
use App\Message\UserValidatedMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

class UserValidatedMessageHandler implements
    MessageHandlerInterface,
    ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    /**
     * Send the process owner a notification email.
     *
     * @param UserRegisteredMessage $message
     */
    public function __invoke(UserValidatedMessage $message)
    {
        $entityManager = $this->entityManager();
        $user = $entityManager->getRepository(User::class)
            ->findOneBy([
                'id'          => $message->userId,
                'isValidated' => true,
                'deletedAt'   => null,
            ]);

        if (!$user) {
            $this->logger()->info(
                "User {$message->userId} does not exist or is not validated!"
            );

            return;
        }

        $sent = $this->sendNotificationMail($user);
        if ($sent) {
            $this->logger()
                ->info("Sent new-user notification email!");
        } else {
            $this->logger()
                ->error("Failed to send the new-user notification email!");
        }
    }

    /**
     * Sends an email with the validation details (URL to click) to the new user.
     *
     * @param Validation $validation
     * @param string $url
     * @return bool
     */
    private function sendNotificationMail(User $user): bool
    {
        $admins = $this->getProcessOwners();
        if (!count($admins)) {
            $this->logger()
                ->error("No process owners found!");
            return false;
        }

        $email = (new TemplatedEmail())
            // FROM is added via listener
            ->subject('Neuer Benutzer bestätigt') // @todo translate
            ->htmlTemplate('registration/mail.user-validated-notification.html.twig')
            ->context([
                'username'  => $user->getUsername(),
                'useremail' => $user->getEmail(),
            ]);

        foreach($admins as $admin) {
            $email->addTo($admin->getEmail());
        }

        $sent = $this->mailer()->send($email);
        return $sent instanceof SentMessage && $sent->getMessageId() !== null;
    }

    /**
     * Load all active process owners.
     *
     * @todo what is the correct way to filter by role?
     * @todo add to UserRepository
     * @return User[]
     */
    protected function getProcessOwners()
    {
        return $this->entityManager()
            ->createQuery(
                'SELECT u
                FROM App\Entity\User u
                WHERE
                    u.roles LIKE :role
                    AND u.deletedAt IS NULL
                    AND u.isValidated = 1'
            )
            ->setParameter('role', '%ROLE_PROCESS_OWNER%')
            ->getResult();
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->container->get(__METHOD__);
    }

    private function logger(): LoggerInterface
    {
        return $this->container->get(__METHOD__);
    }

    private function mailer(): TransportInterface
    {
        return $this->container->get(__METHOD__);
    }
}
