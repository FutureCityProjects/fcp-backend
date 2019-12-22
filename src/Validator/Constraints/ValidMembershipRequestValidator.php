<?php
declare(strict_types=1);

namespace App\Validator\Constraints;

use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\User;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ValidMembershipRequestValidator extends ConstraintValidator
{
    /**
     * @var Security
     */
    protected $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    /**
     * {@inheritDoc}
     *
     * @param mixed $value
     * @param Constraint $constraint
     * @throws UnexpectedTypeException
     */
    public function validate($object, Constraint $constraint)
    {
        if (!$object instanceof ProjectMembership) {
            throw new UnexpectedTypeException($object, Project::class);
        }

        $project = $object->getProject();
        if (!$object->getRole() || !$project || !$object->getUser()) {
            // should be handled by a NotBlank constraint
            return;
        }

        if ($project->getProgress() === Project::PROGRESS_IDEA) {
            $this->context
                ->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        if ($project->userIsMember($object->getUser())) {
            // User can only have one membership type -> handled by the
            // unique entity validator
            return;
        }

        $currentUser = $this->security->getUser();

        // we require a logged in user to continue
        // @todo refactor to work in the messenger queue?
        if (!$currentUser instanceof User) {
            $this->context
                ->buildViolation($constraint->message)
                ->addViolation();
        }

        if ($project->userIsOwner($currentUser)) {
            // Project owner can only add members, there should be only one owner
            // -> transfer ownership
            // @todo implement ownership transfer, implement invitation to
            // participate via Validations
            if ($object->getRole() !== ProjectMembership::ROLE_MEMBER) {
                $this->context
                    ->buildViolation($constraint->message)
                    ->addViolation();
            }
            return;
        }

        // a user can only:
        // * apply for a membership, not add himself as member/owner
        // * apply to unlocked, visible projects
        // * apply himself, not another user
        if ($object->getRole() !== ProjectMembership::ROLE_APPLICANT
            || $project->isLocked()
            || $project->getState() === Project::STATE_DEACTIVATED
            || $currentUser->getId() !== $object->getUser()->getId()
        ) {
            $this->context
                ->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
