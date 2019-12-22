<?php
declare(strict_types=1);

namespace App\Validator;

use App\Entity\User;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class UserValidator
{
    /**
     * Called when a user is saved.
     *
     * @param User $object
     * @param ExecutionContextInterface $context
     * @param $payload
     */
    public static function validateUpdate(User $object, ExecutionContextInterface $context, $payload)
    {

    }
}
