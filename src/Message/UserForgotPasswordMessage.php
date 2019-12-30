<?php
declare(strict_types=1);

namespace App\Message;

/**
 * Dispatched to send the user a validation email with a confirmation URL at
 * which he can reset his password.
 */
class UserForgotPasswordMessage extends UserMessage
{
}
