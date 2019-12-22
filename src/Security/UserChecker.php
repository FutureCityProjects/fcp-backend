<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @todo translate doc
 */
class UserChecker implements UserCheckerInterface
{
    /**
     * Welche Meldungen sollen angezeigt werden bevor das Passwort geprüft wird?
     *
     * @param UserInterface $user
     */
    public function checkPreAuth(UserInterface $user)
    {
        if (!$user instanceof User) {
            return;
        }

        // isDeleted wird bereits im UserRepository geprüft
    }

    /**
     * Welche Meldungen sollen angezeigt werden nachdem das Passwort geprüft wurde?
     * Er hat sich hier also schon authentifiziert, wir können ihm interne Details
     * anzeigen.
     *
     * @param UserInterface $user
     */
    public function checkPostAuth(UserInterface $user)
    {
        if (!$user instanceof User) {
            return;
        }

        if (! $user->isValidated()) {
            throw new Exception\AccountNotValidatedException();
        }

        if (! $user->isActive()) {
            throw new Exception\AccountNotActivatedException();
        }
    }
}
