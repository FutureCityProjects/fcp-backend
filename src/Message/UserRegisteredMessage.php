<?php
declare(strict_types=1);

namespace App\Message;

/**
 * An user registered, send a validation email.
 * The validation URL depends on the client and needs to be injected.
 */
class UserRegisteredMessage extends UserMessage
{
    public string $validationUrl;

    public function __construct(int $userId, string $validationUrl)
    {
        parent::__construct($userId);
        $this->validationUrl = $validationUrl;
    }
}
