<?php
declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class UnmodifiedIdea extends Constraint
{
    public string $message = "Idea cannot be modified.";

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
