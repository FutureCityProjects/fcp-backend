<?php
declare(strict_types=1);

namespace App\Event;

use App\Entity\Validation;

/**
 * Triggered when the validation confirmation URL was called with an token that
 * exists in the database but is expired or when expired validations are purged
 * via cron job.
 */
class ValidationExpiredEvent extends ValidationEvent
{
    /**
     * @var bool true when the event is triggered via cron/queue processing
     * to let the listener know if he needs to remove the validation & flush the
     * entityManager.
     */
    public bool $isPurgeEvent;

    public function __construct(Validation $validation, bool $isPurgeEvent = false)
    {
        parent::__construct($validation);
        $this->isPurgeEvent = $isPurgeEvent;
    }
}
