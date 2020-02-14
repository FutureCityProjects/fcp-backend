<?php
declare(strict_types=1);

namespace App\Validator;

use App\Entity\Project;
use App\Entity\ProjectMembership;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ProjectMembershipValidator
{
    /**
     * Called when a projectMembership is created via user registration.
     *
     * @param ProjectMembership $object
     * @param ExecutionContextInterface $context
     * @param $payload
     */
    public static function validateRegistration(ProjectMembership $object, ExecutionContextInterface $context, $payload)
    {
        // hack: this method is also called when a user registers with a new
        // project he wants to create, in this case the role is ROLE_OWNER.
        // We have no way to check from where this membership comes so we just
        // skip this validation if we have no user.
        if (!$object->getUser()) {
            return;
        }

        if ($object->getRole() !== ProjectMembership::ROLE_APPLICANT) {
            $context->buildViolation('validate.projectMembership.invalidRequest')
                ->addViolation()
            ;
            return;
        }

        $project = $object->getProject();
        if (!$project
            || $project->getProgress() === Project::PROGRESS_IDEA
            || $project->getState() === Project::STATE_DEACTIVATED
            || $project->isLocked()
            || $project->isDeleted()
            || $project->userIsMember($object->getUser())
        ) {
            $context->buildViolation('validate.projectMembership.invalidRequest')
                ->addViolation()
            ;
            return;
        }
    }
}
