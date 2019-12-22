<?php

namespace App\Security\Exception;

use Symfony\Component\Security\Core\Exception\AccountStatusException;

class AccountNotValidatedException extends AccountStatusException
{
    /**
     * {@inheritdoc}
     */
    public function getMessageKey()
    {
        return 'Account is not validated.';
    }
}
