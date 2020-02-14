<?php
declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class ValidMembershipRequest extends Constraint
{
    public string $message = "validate.projectMembership.invalidRequest";

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
