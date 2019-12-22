<?php

namespace App\Security\Exception;

use Symfony\Component\Security\Core\Exception\AccountStatusException;

class AccountNotActivatedException extends AccountStatusException
{
    /**
     * {@inheritdoc}
     */
    public function getMessageKey()
    {
        return 'Account is not activated.';
    }
}
