<?php
declare(strict_types=1);

namespace App\Event;

use App\Entity\User;

/**
 * Triggered when an user registers via the API.
 */
class UserRegisteredEvent extends UserEvent
{
    /**
     * @var string
     */
    public string $validationUrl;

    public function __construct(User $user, string $validationUrl)
    {
        parent::__construct($user);
        $this->validationUrl = $validationUrl;
    }
}
