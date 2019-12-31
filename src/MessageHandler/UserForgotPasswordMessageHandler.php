<?php
declare(strict_types=1);

namespace App\MessageHandler;


use App\Entity\User;
use App\Entity\Validation;
use App\Message\UserForgotPasswordMessage;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

class UserForgotPasswordMessageHandler implements
    MessageHandlerInterface,
    ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    /**
     * Create the validation token and send the user an email with a link to click.
     *
     * @param UserForgotPasswordMessage $message
     */
    public function __invoke(UserForgotPasswordMessage $message)
    {
        $this->logger()->debug(
            'Processing UserForgotPasswordMessage for user '
            .$message->userId
        );

        $entityManager = $this->entityManager();
        $user = $entityManager->getRepository(User::class)
            ->findOneBy([
                'id'          => $message->userId,
                'deletedAt'   => null,
            ]);

        if (!$user) {
            $this->logger()->info(
                "User {$message->userId} does not exist!"
            );

            return;
        }

        // @todo check if a (valid) pw reset validation exists -> cancel

        $validation = $this->createValidation($user);

        $sent = $this->sendValidationMail($validation, $message->validationUrl);
        if ($sent) {
            $this->logger()
                ->info("Sent password reset validation email to user {$user->getEmail()}!");
        } else {
            $this->logger()
                ->error("Failed to send the password reset validation email to {$user->getEmail()}!");
        }
    }

    /**
     * @param User $user
     *
     * @return Validation
     * @throws Exception when token generation fails
     */
    private function createValidation(User $user): Validation
    {
        $validation = new Validation();
        $validation->setUser($user);
        $validation->setType(Validation::TYPE_RESET_PASSWORD);
        $validation->generateToken();

        // @todo make interval configurable
        $now = new DateTimeImmutable();
        $validUntil = $now->add(new DateInterval('P2D'));
        $validation->setExpiresAt($validUntil);

        $entityManager = $this->entityManager();
        $entityManager->persist($validation);
        $entityManager->flush();

        return $validation;
    }

    /**
     * Sends an email with the validation link (URL to click) to the new user,
     * the client should show a form to enter a new password on this URL.
     *
     * @param Validation $validation
     * @param string $url
     * @return bool
     */
    private function sendValidationMail(Validation $validation, string $url): bool
    {
        // replace the placeholders, token & id are required and enforced via
        // constraint on the UserInput DTO, type is optional as information for
        // the client (which message to show on failed validation)
        $withToken = str_replace('{{token}}', $validation->getToken(), $url);
        $withId = str_replace('{{id}}', $validation->getId(), $withToken);
        $withType = str_replace('{{type}}', $validation->getType(), $withId);

        $email = (new TemplatedEmail())
            // FROM is added via listener
            ->to($validation->getUser()->getEmail())
            ->subject('Passwort zurücksetzen') // @todo translate
            ->htmlTemplate('security/mail.password-reset.html.twig')
            ->context([
                'expiresAt'     => $validation->getExpiresAt(),
                'username'      => $validation->getUser()->getUsername(),
                'validationUrl' => $withType,
            ]);

        $sent = $this->mailer()->send($email);
        return $sent instanceof SentMessage && $sent->getMessageId() !== null;
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
