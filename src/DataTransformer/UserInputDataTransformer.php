<?php
declare(strict_types=1);

namespace App\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use App\Dto\UserInput;
use App\Entity\User;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * Handles password encoding and setting a random password if none was given.
 */
class UserInputDataTransformer implements DataTransformerInterface
{
    /**
     * @var UserPasswordEncoderInterface
     */
    private $passwordEncoder;

    public function __construct(UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->passwordEncoder = $passwordEncoder;
    }

    /**
     * {@inheritdoc}
     *
     * @param UserInput $data
     * @return User
     */
    public function transform($data, string $to, array $context = [])
    {
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
                $this->passwordEncoder->encodePassword($user, $data->password)
            );
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
}
