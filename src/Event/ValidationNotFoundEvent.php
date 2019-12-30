<?php
declare(strict_types=1);

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * To allow locking IP / user after too many failed validations etc.
 */
class ValidationNotFoundEvent extends Event
{
}
