<?php
declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Header\MailboxListHeader;
use Symfony\Component\Mime\Message;

/**
 * Adds a FROM address to every mail that has none set.
 * The address is configured in ENV|.env via MAILER_SENDER and injected in the
 * services.yaml definition.
 *
 * This replaces setting the sender via mailer.yaml as envelope
 * (@see https://symfonycasts.com/screencast/mailer/event-global-recipients)
 * as this would still require each mail to have a FROM address set and also
 * doesn't allow us to set a sender name.
 */
class MailerEventSubscriber implements EventSubscriberInterface
{
    /**
     * @var string
     */
    private $sender;

    public function __construct(string $sender)
    {
        $this->sender = $sender;
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            MessageEvent::class => 'onMessage',
        ];
    }

    public function onMessage(MessageEvent $event)
    {
        $message = $event->getMessage();
        if (!$message instanceof Message) {
            return;
        }

        if ($message->getHeaders()->has('From')) {
            return;
        }

        $message->getHeaders()->add(
            new MailboxListHeader('From', [
                Address::fromString($this->sender)
            ])
        );
    }
}
