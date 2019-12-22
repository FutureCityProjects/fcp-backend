<?php
declare(strict_types=1);

namespace App\Validator\Constraints;

use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * @todo refactor to be usable with ODM/MongoDB too
 */
class UnmodifiedIdeaValidator extends ConstraintValidator
{
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $manager;

    public function __construct(EntityManagerInterface $manager)
    {
        $this->manager = $manager;
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
        if (!$object instanceof Project) {
            throw new UnexpectedTypeException($object, Project::class);
        }

        if (!$object->getId()) {
            throw new UnexpectedValueException('new Project',
                'persisted Project');
        }

        $oldObject = $this->manager->getUnitOfWork()
            ->getOriginalEntityData($object);

        if ($object->getProgress() === Project::PROGRESS_IDEA
            && $object->getShortDescription() !== $oldObject['shortDescription']
        ) {
            $this->context
                ->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
