<?php
declare(strict_types=1);

namespace App\Event;

use App\Entity\Validation;

/**
 * Triggered when the validation confirmation URL was called with a valid token.
 */
class ValidationConfirmedEvent extends ValidationEvent
{
    /**
     * @var array Additional parameters passed by the client, e.g. the new
     * password after pw reset.
     */
    public array $params;

    public function __construct(Validation $validation, array $params = [])
    {
        parent::__construct($validation);
        $this->params = $params;
    }
}
