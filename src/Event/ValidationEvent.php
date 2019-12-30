<?php
declare(strict_types=1);

namespace App\Event;

use App\Entity\Validation;

abstract class ValidationEvent
{
    public Validation $validation;

    public function __construct(Validation $validation)
    {
        $this->validation = $validation;
    }
}
