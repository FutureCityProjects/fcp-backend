<?php
declare(strict_types=1);

namespace App\Event;

use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for events triggered for an user account.
 */
abstract class UserEvent extends Event
{
    public User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }
}
