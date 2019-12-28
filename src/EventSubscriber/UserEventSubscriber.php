<?php
declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\UserRegisteredEvent;
use App\Message\UserRegisteredMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Security;

/**
 * Listens to different events regarding users, to push necessary tasks to the
 * message queue for asynchronous execution.
 */
class UserEventSubscriber implements EventSubscriberInterface
{
    /**
     * @var MessageBusInterface
     */
    private $bus;

    /**
     * @var Security
     */
    private Security $security;

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

    public function __construct(MessageBusInterface $bus, Security $security)
    {
        $this->bus = $bus;
        $this->security = $security;
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

        $this->bus->dispatch(
            new UserRegisteredMessage($event->user->getId(), $event->validationUrl)
        );
    }
}
