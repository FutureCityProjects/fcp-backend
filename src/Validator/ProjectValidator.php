<?php
declare(strict_types=1);

namespace App\Validator;

use App\Entity\Helper\ProjectHelper;
use App\Entity\Project;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
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
            $context->buildViolation('validate.project.inspiration.notBlank')
                ->atPath('inspiration')
                ->addViolation()
            ;
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

        /*
         * @todo empty name is allowed, maybe prevent removing an existing
         * name so it cannot get unnamed again?
        if (empty($object->getName())) {
            $context->buildViolation('This value should not be blank.')
                ->atPath('name')
                ->addViolation();
        }
        */
    }

    public static function validateTasks($value, ExecutionContextInterface $context, $payload)
    {
        /** @var Project $project */
        $project = $context->getObject();

        if (!$project instanceof Project) {
            throw new UnexpectedTypeException($project, Project::class);
        }

        if (empty($value)) {
            return;
        }

        $helper = new ProjectHelper($project);

        if ($helper->hasDuplicateTaskIDs()) {
            $context->buildViolation('validate.project.duplicateTaskIDs')
                ->addViolation();
            return;
        }
    }

    public static function validateWorkPackages($value, ExecutionContextInterface $context, $payload)
    {
        /** @var Project $project */
        $project = $context->getObject();

        if (!$project instanceof Project) {
            throw new UnexpectedTypeException($project, Project::class);
        }

        if (empty($value)) {
            return;
        }

        $helper = new ProjectHelper($project);

        if ($helper->hasDuplicatePackageIDs()) {
            $context->buildViolation('validate.project.duplicatePackageIDs')
                ->addViolation();
            return;
        }

        if ($helper->hasDuplicatePackageNames()) {
            $context->buildViolation('validate.project.duplicatePackageNames')
                ->addViolation();
            return;
        }
    }
}
