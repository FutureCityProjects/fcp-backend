<?php
declare(strict_types=1);

namespace App\Validator\Constraints;

use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ValidMembershipUpdateValidator extends ConstraintValidator
{
    /**
     * @var EntityManagerInterface
     */
    protected EntityManagerInterface $manager;

    /**
     * @var Security
     */
    protected $security;

    public function __construct(EntityManagerInterface $manager, Security $security)
    {
        $this->manager = $manager;
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
        $member = $object->getUser();
        if (!$project || !$member || !$object->getRole()) {
            // should be handled by a NotBlank constraint
            return;
        }

        $oldObject = $this->manager->getUnitOfWork()
            ->getOriginalEntityData($object);
        $oldRole = $oldObject['role'];
        $newRole = $object->getRole();

        // user and project cannot be changed
        if ($member->getId() !== $oldObject['user']->getId()
            || $project->getId() !== $oldObject['project']->getId()
        ) {
            // should be already handled by serialization group annotation
            // and return "extra attributes user|project not allowed"
            $this->context
                ->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        if ($project->isDeleted() || $project->isLocked()) {
            $this->context
                ->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        $currentUser = $this->security->getUser();

        // we require a logged in user to continue
        // @todo refactor to work in the messenger queue?
        if (!$currentUser instanceof User) {
            $this->context
                ->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        if ($currentUser->getId() === $member->getId()) {
            // a user can not change his own role
            if ($newRole !== $oldRole) {
                $this->context
                    ->buildViolation($constraint->message)
                    ->addViolation();
                return;
            }

            // but he can everything else with his own membership -> return here
            return;
        }

        if ($project->userIsOwner($currentUser)) {
            // Project owners can only edit applicants
            if ($oldRole !== ProjectMembership::ROLE_APPLICANT) {
                $this->context
                    ->buildViolation($constraint->message)
                    ->addViolation();
                return;
            }

            // a project owner can only modify the application or upgrade it
            // to a normal membership
            if ($newRole !== ProjectMembership::ROLE_APPLICANT
                && $newRole !== ProjectMembership::ROLE_MEMBER
            ) {
                $this->context
                    ->buildViolation($constraint->message)
                    ->addViolation();
                return;
            }

            return;
        }

        // the user is not the project owner and it's not his own membership
        // -> he can only be admin or process owner, everything else is forbidden
        // by the Voter

        // role cannot be changed here: Downgrade to Applicant is forbidden,
        // upgrade to owner or downgrade from owner to member uses an extra
        // endpoint to ensure there is always exactly one owner
        if ($newRole !== $oldRole) {
            $this->context
                ->buildViolation($constraint->message)
                ->addViolation();
            return;
        }
    }
}
