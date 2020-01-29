<?php
declare(strict_types=1);

namespace App\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use ApiPlatform\Core\Exception\DeserializationException;
use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use ApiPlatform\Core\Validator\ValidatorInterface;
use App\Dto\ProjectInput;
use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Handles setting the creator.
 */
class ProjectInputDataTransformer implements DataTransformerInterface
{
    /**
     * @var User
     */
    private $user;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * ProjectInputDataTransformer constructor.
     * @param TokenStorageInterface $tokenStorage
     * @throws DeserializationException when no authenticated user is found
     */
    public function __construct(
        TokenStorageInterface $tokenStorage, ValidatorInterface $validator)
    {
        $this->user = $tokenStorage->getToken()
            ? $tokenStorage->getToken()->getUser()
            : null;
        $this->validator = $validator;
    }

    /**
     * {@inheritdoc}
     *
     * @param ProjectInput $data
     * @return Project
     */
    public function transform($data, string $to, array $context = [])
    {
        // this evaluates all constraint annotations on the DTO
        $context['groups'][] = 'Default';
        $this->validator->validate($data, $context);

        /* @var $project Project */
        $project = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE]
            ?? new Project();

        if ($data->isLocked !== null) {
            $project->setIsLocked($data->isLocked);
        }

        if ($data->process) {
            $project->setProcess($data->process);
        }

        if ($data->progress) {
            $project->setProgress($data->progress);
        }

        if ($data->state !== null) {
            $project->setState($data->state);
        }

        // creator is optional, we can create projects when a user registers
        // so the creator is set afterwards by the userInput Transformer
        if (!$project->getId() && $this->user instanceof UserInterface) {
            $project->setCreatedBy($this->user);
        }

        // inspiration can only be set when the project is created
        if ($data->inspiration) {
            $project->setInspiration($data->inspiration);

            // initial value
            $project->setShortDescription(
                $data->inspiration->getShortDescription());
        }

        // When a user creates a new project set him as owner.
        if ($data->progress === Project::PROGRESS_CREATING_PROFILE
            && !$project->getId()
        ) {
            $membership = new ProjectMembership();
            $membership->setRole(ProjectMembership::ROLE_OWNER);
            $project->addMembership($membership);

            // This can also happen when a user registers, then the user is
            // set afterwards by the userInput transformer.
            if ($this->user instanceof UserInterface) {
                $membership->setUser($this->user);
            }

            if ($data->motivation !== null) {
                $membership->setMotivation($data->motivation);
            }

            if ($data->skills !== null) {
                $membership->setSkills($data->skills);
            }

            $this->validator->validate($membership, $context);
        }

        $this->setProfileData($data, $project);
        $this->setPlanData($data, $project);

        return $project;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof Project) {
            return false;
        }

        return Project::class === $to && null !== ($context['input']['class'] ?? null);
    }

    protected function setProfileData(ProjectInput $data, Project $project)
    {
        if ($data->challenges !== null) {
            $project->setChallenges($data->challenges);
        }

        if ($data->delimitation !== null) {
            $project->setDelimitation($data->delimitation);
        }

        if ($data->description !== null) {
            $project->setDescription($data->description);
        }

        if ($data->goal !== null) {
            $project->setGoal($data->goal);
        }

        if ($data->name !== null) {
            $project->setName($data->name);
        }

        if ($data->profileSelfAssessment !== null) {
            $project->setProfileSelfAssessment($data->profileSelfAssessment);
        }

        if ($data->shortDescription !== null) {
            $project->setShortDescription($data->shortDescription);
        }

        if ($data->vision !== null) {
            $project->setVision($data->vision);
        }
    }

    protected function setPlanData(ProjectInput $data, Project $project)
    {
        if ($data->impact !== null) {
            $project->setImpact($data->impact);
        }

        if ($data->implementationTime !== null) {
            $project->setImplementationTime($data->implementationTime);
        }

        if ($data->outcome !== null) {
            $project->setOutcome($data->outcome);
        }

        if ($data->planSelfAssessment !== null) {
            $project->setPlanSelfAssessment($data->planSelfAssessment);
        }

        if ($data->results !== null) {
            $project->setResults($data->results);
        }

        if ($data->utilization !== null) {
            $project->setUtilization($data->utilization);
        }

        if ($data->targetGroups !== null) {
            $project->setTargetGroups($data->targetGroups);
        }

        if ($data->tasks !== null) {
            $project->setTasks($data->tasks);
        }

        if ($data->workPackages !== null) {
            $project->setWorkPackages($data->workPackages);
        }
    }
}
