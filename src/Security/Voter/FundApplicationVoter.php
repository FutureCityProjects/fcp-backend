<?php

namespace App\Security\Voter;

use App\Entity\Fund;
use App\Entity\FundApplication;
use App\Entity\Project;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class FundApplicationVoter extends Voter
{
    /**
     * {@inheritdoc}
     */
    protected function supports($attribute, $subject)
    {
        return in_array($attribute, ['CREATE', 'EDIT', 'DELETE'])
            && $subject instanceof FundApplication;
    }

    /**
     * {@inheritdoc}
     *
     * @param FundApplication $subject
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();
        // if the user is anonymous, do not grant access
        if (!$user instanceof UserInterface) {
            return false;
        }

        switch ($attribute) {
            case 'CREATE':
                if (!$subject->getFund() || !$subject->getProject()) {
                    return true; // should be checked by NotBlank constraint
                }

                if ($subject->getProject()->isLocked()
                    || $subject->getProject()->getProgress() == Project::PROGRESS_IDEA
                ) {
                    return false;
                }

                // @todo Abgleich mit Pflichtenheft
                if ($subject->getFund()->getState() !== Fund::STATE_ACTIVE) {
                    dump('Fund nicht aktiv');
                    return false;
                }

                // @todo only possible when project state allows

                // @todo Abgleich mit Pflichtenheft
                $isOwner = $subject->getProject()->userIsOwner($user);
                dump('User is owner? '.($isOwner ? 'ja':'ne'));
                return $isOwner;
                break;

            case 'EDIT':
                if ($user->hasRole(User::ROLE_ADMIN)
                    || $user->hasRole(User::ROLE_PROCESS_OWNER)
                ) {
                    return true;
                }

                if ($subject->getProject()->isLocked()) {
                    return false;
                }

                // @todo Abgleich mit Pflichtenheft
                if ($subject->getFund()->getState() !== Fund::STATE_ACTIVE) {
                    return false;
                }

                // @todo Abgleich mit Pflichtenheft
                if ($subject->getState() === FundApplication::STATE_SUBMITTED) {
                    return false;
                }

                return $subject->getProject()->userIsMember($user);
                break;

            case 'DELETE':
                // @todo Abgleich mit Pflichtenheft
                if ($user->hasRole(User::ROLE_ADMIN)
                    || $user->hasRole(User::ROLE_PROCESS_OWNER)
                ) {
                    return true;
                }

                if ($subject->getProject()->isLocked()) {
                    return false;
                }

                // @todo Abgleich mit Pflichtenheft
                if ($subject->getFund()->getState() !== Fund::STATE_ACTIVE) {
                    return false;
                }

                // @todo Abgleich mit Pflichtenheft
                if ($subject->getState() === FundApplication::STATE_SUBMITTED) {
                    return false;
                }

                return $subject->getProject()->userIsOwner($user);
                break;

            // @todo add privilege "SUBMIT", check application state
        }

        return false;
    }
}
