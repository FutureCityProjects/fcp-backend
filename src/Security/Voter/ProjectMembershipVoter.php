<?php

namespace App\Security\Voter;

use App\Entity\ProjectMembership;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class ProjectMembershipVoter extends Voter
{
    /**
     * {@inheritdoc}
     */
    protected function supports($attribute, $subject)
    {
        return in_array($attribute, ['EDIT', 'DELETE'])
            && $subject instanceof ProjectMembership;
    }

    /**
     * {@inheritdoc}
     *
     * @param ProjectMembership $subject
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
                // admins can edit any membership
                if ($user->hasRole(User::ROLE_ADMIN)
                    || $user->hasRole(User::ROLE_PROCESS_OWNER)
                ) {
                    return true;
                }

                if ($subject->getProject()->isLocked()
                    || $subject->getProject()->isDeleted()
                ) {
                    return false;
                }

                // users can edit their own membership/application
                if ($user->getId() == $subject->getUser()->getId()) {
                    return true;
                }

                // project owners can edit applications/memberships for other
                // members of their project
                return $subject->getProject()->userIsOwner($user);
                break;

            case 'DELETE':
                // admins can delete any membership
                if ($user->hasRole(User::ROLE_ADMIN)
                    || $user->hasRole(User::ROLE_PROCESS_OWNER)
                ) {
                    return true;
                }

                if ($subject->getProject()->userIsOwner($user)) {
                    // owners cannot delete their own membership -> additional
                    // workflow
                    return $subject->getUser()->getId() !== $user->getId();
                }

                // members can remove themselves from a project / retract
                // their application
                return $subject->getUser()->getId() === $user->getId();
                break;
        }

        return false;
    }
}
