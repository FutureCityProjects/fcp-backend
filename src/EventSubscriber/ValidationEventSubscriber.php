<?php
declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\CronDailyEvent;
use App\Message\PurgeValidationsMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

class ValidationEventSubscriber
    implements EventSubscriberInterface, ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    public static function getSubscribedEvents()
    {
        return [
            CronDailyEvent::class => [
                ['onCronDaily', 100],
            ],
        ];
    }

    public function onCronDaily()
    {
        $this->messageBus()->dispatch(new PurgeValidationsMessage());
        $this->logger()->debug('Daily request to purge expired validation was sent to the message queue.');
    }

    private function logger(): LoggerInterface
    {
        return $this->container->get(__METHOD__);
    }

    private function messageBus(): MessageBusInterface
    {
        return $this->container->get(__METHOD__);
    }
}
