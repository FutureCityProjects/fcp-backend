<?php
declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class ValidMembershipUpdate extends Constraint
{
    public string $message = "Membership update is not valid.";

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
