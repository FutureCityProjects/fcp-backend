<?php
declare(strict_types=1);

namespace App\Message;

/**
 * An user requested an email change, send a validation email.
 * The validation URL depends on the client and needs to be injected.
 */
class UserEmailChangeMessage extends UserMessage
{
    public string $validationUrl;

    public string $newEmail;

    public function __construct(int $userId, string $newEmail, string $validationUrl)
    {
        parent::__construct($userId);
        $this->newEmail = $newEmail;
        $this->validationUrl = $validationUrl;
    }
}
