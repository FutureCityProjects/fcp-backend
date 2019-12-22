<?php
declare(strict_types=1);

namespace App\Validator;

use App\Entity\Project;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ProjectValidator
{
    /**
     * Called when a project is created.
     *
     * @param Project $object
     * @param ExecutionContextInterface $context
     * @param $payload
     */
    public static function validateCreation(Project $object, ExecutionContextInterface $context, $payload)
    {
        if ($object->getProgress() === Project::PROGRESS_IDEA) {
            // @todo check that no additional properties are set
            return;
        }

        // only Project::PROGRESS_IDEA and Project::PROGRESS_CREATING_PROFILE
        // are allowed by the Choice constraint, don't show other violations
        if ($object->getProgress() !== Project::PROGRESS_CREATING_PROFILE) {
            return;
        }

        if (!$object->getInspiration()) {
            $context->buildViolation('Inspiration is required for new projects.')
                ->atPath('inspiration')
                ->addViolation()
            ;
        }

        if (empty($object->getName())) {
            $context->buildViolation('This value should not be blank.')
                ->atPath('name')
                ->addViolation();
        }
    }

    /**
     * Called when a project is updated
     *
     * @param Project $object
     * @param ExecutionContextInterface $context
     * @param $payload
     */
    public static function validateUpdate(Project $object, ExecutionContextInterface $context, $payload)
    {
        if ($object->getProgress() === Project::PROGRESS_IDEA) {
            return;
        }

        if (empty($object->getName())) {
            $context->buildViolation('This value should not be blank.')
                ->atPath('name')
                ->addViolation();
        }
    }
}
