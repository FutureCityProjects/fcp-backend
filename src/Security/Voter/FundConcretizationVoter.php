<?php

namespace App\Security\Voter;

use App\Entity\Fund;
use App\Entity\FundConcretization;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class FundConcretizationVoter extends Voter
{
    /**
     * {@inheritdoc}
     */
    protected function supports($attribute, $subject)
    {
        return in_array($attribute, ['EDIT', 'DELETE'])
            && $subject instanceof FundConcretization;
    }

    /**
     * {@inheritdoc}
     *
     * @param FundConcretization $subject
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
                // @todo can concretizations still be deleted when the fund
                // is already active?

                if ($user->hasRole(User::ROLE_ADMIN)
                    || $user->hasRole(User::ROLE_PROCESS_OWNER)
                ) {
                    return true;
                }
                break;

            case 'DELETE':
                // @todo can concretizations still be deleted when the fund
                // is already active?
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
