<?php

namespace App\Security\Voter;

use App\Entity\Fund;
use App\Entity\JuryCriterion;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class JuryCriterionVoter extends Voter
{
    /**
     * {@inheritdoc}
     */
    protected function supports($attribute, $subject)
    {
        return in_array($attribute, ['EDIT', 'DELETE'])
            && $subject instanceof JuryCriterion;
    }

    /**
     * {@inheritdoc}
     *
     * @param JuryCriterion $subject
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();
        // if the user is anonymous, do not grant access
        if (!$user instanceof UserInterface) {
            return false;
        }

        switch ($attribute) {
            case 'EDIT':
                if ($user->hasRole(User::ROLE_ADMIN)
                    || $user->hasRole(User::ROLE_PROCESS_OWNER)
                ) {
                    return true;
                }
                break;

            case 'DELETE':
                // @todo can criteria still be deleted when the fund is
                // already active but submission has not ended / rating has not
                // started?
                if ($subject->getFund()->getState() === Fund::STATE_ACTIVE) {
                    return false;
                }

                if ($user->hasRole(User::ROLE_ADMIN)
                    || $user->hasRole(User::ROLE_PROCESS_OWNER)
                ) {
                    return true;
                }
                break;
        }

        return false;
    }
}
