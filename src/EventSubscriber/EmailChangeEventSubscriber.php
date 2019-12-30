<?php
declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Entity\Validation;
use App\Event\ValidationConfirmedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

/**
 * Listens to the (email-change) validation events.
 */
class EmailChangeEventSubscriber
    implements EventSubscriberInterface, ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    public static function getSubscribedEvents()
    {
        return [
            ValidationConfirmedEvent::class => [
                ['onValidationConfirmed', 100],
            ],

            // nothing to do for expired email change, the validation object
            // is removed by the event trigger
            //ValidationExpiredEvent::class => [
            //    ['onValidationExpired', 100],
            //],
        ];
    }

    public function onValidationConfirmed(ValidationConfirmedEvent $event)
    {
        if ($event->validation->getType() !== Validation::TYPE_CHANGE_EMAIL) {
            return;
        }

        $user = $event->validation->getUser();
        if ($user->isDeleted() || !$user->isActive()) {
            throw new NotFoundHttpException('Corresponding user not found.');
        }

        // email change is allowed when the user is logged in, but only
        // the user for which the email change is validated
        if ($this->security()->isGranted(User::ROLE_USER)
            && $this->security()->getUser()->getId() != $user->getId()
        ) {
            throw new AccessDeniedException();
        }

        $user->setEmail($event->validation->getContent()['email']);

        // no need to flush or remove the validation, this is done by the
        // event trigger.
    }

    private function security(): Security
    {
        return $this->container->get(__METHOD__);
    }
}
