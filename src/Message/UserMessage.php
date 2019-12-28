<?php
declare(strict_types=1);

namespace App\Message;

/**
 * Simple serializable message for the message queue processor to find the
 * user account for whom this task is executed.
 */
abstract class UserMessage
{
    public int $userId;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }
}
