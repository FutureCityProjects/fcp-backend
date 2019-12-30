<?php
declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Validation;
use App\Event\ValidationExpiredEvent;
use App\Message\PurgeValidationsMessage;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

class PurgeValidationsMessageHandler implements
    MessageHandlerInterface,
    ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    /**
     * Get all expired validations from the database and remove them, triggering
     * the validation.expired event.
     *
     * @param PurgeValidationsMessage $message
     */
    public function __invoke(PurgeValidationsMessage $message)
    {
        $qb = $this->entityManager()->createQueryBuilder()
            ->select("v")
            ->from(Validation::class, 'v')
            ->where('v.expiresAt <= :now')
            ->setParameters(['now' =>
                new DateTimeImmutable("now", new \DateTimeZone('UTC'))
            ]);

        $expired = $qb->getQuery()->getResult();
        if (!count($expired)) {
            return;
        }

        $this->logger()->debug(
            'Purging '.count($expired).' expired validations from database.'
        );

        foreach ($expired as $validation) {
            // Allow cleanup like removing the non-validated user accounts etc.
            /* @var $validation \App\Entity\Validation */
            $this->dispatcher()->dispatch(
                new ValidationExpiredEvent($validation)
            );

            // make sure the validation is removed
            $this->entityManager()->remove($validation);
        }

        $this->entityManager()->flush();
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return $this->container->get(__METHOD__);
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->container->get(__METHOD__);
    }

    private function logger(): LoggerInterface
    {
        return $this->container->get(__METHOD__);
    }
}
