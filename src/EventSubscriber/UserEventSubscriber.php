<?php
declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\UserRegisteredEvent;
use App\Message\UserRegisteredMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

/**
 * Listens to different events regarding users, to push necessary tasks to the
 * message queue for asynchronous execution.
 */
class UserEventSubscriber
    implements EventSubscriberInterface, ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            UserRegisteredEvent::class => [
                ['onApiUserCreated', 100],
            ],
        ];
    }

    /**
     * Send the validation email asynchronously to reduce load time.
     *
     * @param UserRegisteredEvent $event
     */
    public function onApiUserCreated(UserRegisteredEvent $event)
    {
        // if validation is not required -> do nothing
        if ($event->user->isValidated()) {
            return;
        }

        $this->messageBus()->dispatch(
            new UserRegisteredMessage($event->user->getId(), $event->validationUrl)
        );
    }

    private function messageBus(): MessageBusInterface
    {
        return $this->container->get(__METHOD__);
    }
}
