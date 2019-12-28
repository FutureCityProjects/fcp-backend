<?php
declare(strict_types=1);

namespace App\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use ApiPlatform\Core\Exception\DeserializationException;
use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use ApiPlatform\Core\Validator\ValidatorInterface;
use App\Dto\ProjectInput;
use App\Entity\Project;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

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
        if (!$tokenStorage->getToken()
            || !$tokenStorage->getToken()->getUser()
        ) {
            throw new DeserializationException(
                'User must be set to create project.');
        }

        $this->user = $tokenStorage->getToken()->getUser();
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

        if (!$project->getId()) {
            $project->setCreatedBy($this->user);
        }

        if (isset($data->challenges) !== null) {
            $project->setChallenges($data->challenges);
        }

        if ($data->delimitation !== null) {
            $project->setDelimitation($data->delimitation);
        }

        if ($data->description !== null) {
            $project->setDescription($data->description);
        }

        if ($data->inspiration) {
            $project->setInspiration($data->inspiration);
        }

        if ($data->isLocked !== null) {
            $project->setIsLocked($data->isLocked);
        }

        if ($data->name !== null) {
            $project->setName($data->name);
        }

        if ($data->process) {
            $project->setProcess($data->process);
        }

        if ($data->profileSelfAssessment !== null) {
            $project->setProfileSelfAssessment($data->profileSelfAssessment);
        }

        if ($data->progress) {
            $project->setProgress($data->progress);
        }

        if ($data->shortDescription !== null) {
            $project->setShortDescription($data->shortDescription);
        }

        if ($data->state !== null) {
            $project->setState($data->state);
        }

        if ($data->target !== null) {
            $project->setTarget($data->target);
        }

        if ($data->vision !== null) {
            $project->setVision($data->vision);
        }

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
}
