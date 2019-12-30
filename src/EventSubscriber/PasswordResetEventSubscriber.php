<?php
declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Entity\Validation;
use App\Event\ValidationConfirmedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

/**
 * Listens to the (password-reset) validation events.
 */
class PasswordResetEventSubscriber
    implements EventSubscriberInterface, ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    public static function getSubscribedEvents()
    {
        return [
            ValidationConfirmedEvent::class => [
                ['onValidationConfirmed', 100],
            ],

            // nothing to do for expired pw reset, the validation object
            // is removed by the event trigger
            //ValidationExpiredEvent::class => [
            //    ['onValidationExpired', 100],
            //],
        ];
    }

    public function onValidationConfirmed(ValidationConfirmedEvent $event)
    {
        if ($event->validation->getType() !== Validation::TYPE_RESET_PASSWORD) {
            return;
        }

        if ($this->security()->isGranted(User::ROLE_USER)) {
            throw new AccessDeniedException('Forbidden for logged in users.');
        }

        $user = $event->validation->getUser();
        if ($user->isDeleted() || !$user->isActive()) {
            throw new NotFoundHttpException('Corresponding user not found.');
        }

        if (!isset($event->params['password'])) {
            throw new BadRequestHttpException('Parameter "password" is missing.');
        }

        // @todo validate password

        $user->setPassword(
            $this->passwordEncoder()->encodePassword($user, $event->params['password'])
        );

        // no need to flush or remove the validation, this is done by the
        // event trigger.
    }

    private function passwordEncoder(): UserPasswordEncoderInterface
    {
        return $this->container->get(__METHOD__);
    }

    private function security(): Security
    {
        return $this->container->get(__METHOD__);
    }
}
