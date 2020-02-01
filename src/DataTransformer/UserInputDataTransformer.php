<?php
declare(strict_types=1);

namespace App\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use ApiPlatform\Core\Validator\ValidatorInterface;
use App\Dto\UserInput;
use App\Entity\Project;
use App\Entity\User;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

/**
 * Handles password encoding and setting a random password if none was given.
 */
class UserInputDataTransformer implements DataTransformerInterface,
    ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    /**
     * {@inheritdoc}
     *
     * @param UserInput $data
     * @return User
     */
    public function transform($data, string $to, array $context = [])
    {
        // this evaluates all constraint annotations on the DTO
        $context['groups'][] = 'Default';
        $this->validator()->validate($data, $context);

        $user = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE]
            ?? new User();

        if ($data->username !== null) {
            $user->setUsername($data->username);
        }

        if ($data->email !== null) {
            $user->setEmail($data->email);
        }

        if ($data->roles !== null) {
            $user->setRoles($data->roles);
        }

        if ($data->isValidated !== null) {
            $user->setIsValidated($data->isValidated);
        }

        if ($data->isActive !== null) {
            $user->setIsActive($data->isActive);
        }

        if ($data->firstName !== null) {
            $user->setFirstName($data->firstName);
        }

        if ($data->lastName !== null) {
            $user->setLastName($data->lastName);
        }

        if (!$user->getId() && !$data->password) {
            // no user is allowed to have an empty password
            // -> force-set a unknown random pw here, admin created user must
            // execute the password-reset mechanism
            $data->password = random_bytes(15);
        }

        // we have a (new) password given -> encode and replace the old one
        if ($data->password !== null) {
            $user->setPassword(
                $this->passwordEncoder()->encodePassword($user, $data->password)
            );
        }

        foreach($data->createdProjects as $projectData) {
            // the normalizer already created ProjectInputs from the JSON,
            // now convert to real projects
            $project = $this->projectTransformer()
                ->transform($projectData, Project::class, $context);

            // we don't have an @Assert\Valid on the users createdProjects
            // property as we don't want to validate all projects when only the
            // user data changes -> validate the project here, the
            // projectTransformer above only validated the ProjectInput
            $this->validator()->validate($project, $context);

            // createdProjects can only be set when a user registers ->
            // set all projects/ideas to deactivated, they wil be activated
            // when the user validates
            $project->setState(Project::STATE_DEACTIVATED);

            foreach ($project->getMemberships() as $membership) {
                $user->addProjectMembership($membership);
            }

            $user->addCreatedProject($project);
        }

        return $user;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof User) {
            return false;
        }

        return User::class === $to && null !== ($context['input']['class'] ?? null);
    }

    private function projectTransformer(): ProjectInputDataTransformer
    {
        return $this->container->get(__METHOD__);
    }

    private function passwordEncoder(): UserPasswordEncoderInterface
    {
        return $this->container->get(__METHOD__);
    }

    private function validator(): ValidatorInterface
    {
        return $this->container->get(__METHOD__);
    }
}
