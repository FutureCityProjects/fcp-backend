<?php
declare(strict_types=1);

namespace App\Dto;

use App\Entity\ProjectMembership;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class UserInput
{
    /**
     * @var string
     *
     * @Assert\NotBlank(allowNull=false, groups={"user:resetPassword"})
     * @Groups({
     *     "user:admin-write",
     *     "user:po-write",
     *     "user:register",
     *     "user:resetPassword"
     * })
     */
    public ?string $username = null;

    /**
     * @var string
     *
     * @Assert\NotBlank(allowNull=false, groups={"user:changeEmail"})
     * @Groups({
     *     "user:admin-write",
     *     "user:po-write",
     *     "user:changeEmail",
     *     "user:register"
     * })
     */
    public ?string $email = null;

    /**
     * @var string
     * @Groups({"user:write"})
     * @Assert\Length(min=6, max=200, allowEmptyString=true)
     */
    public ?string $password = null;

    /**
     * @var array
     * @Groups({"user:admin-write", "user:po-write"})
     */
    public ?array $roles = null;

    /**
     * @var bool
     * @Groups({"user:admin-write", "user:po-write"})
     */
    public ?bool $isActive = null;

    /**
     * @var bool
     * @Groups({"user:admin-write", "user:po-write"})
     */
    public ?bool $isValidated = null;

    /**
     * @var string
     * @Groups({"user:write"})
     */
    public ?string $firstName = null;

    /**
     * @var string
     * @Groups({"user:write"})
     */
    public ?string $lastName = null;

    /**
     * no need for @Assert\Valid, the ProjectInputs are validated anyways by
     * the ProjectInputDataTransformer called by the UserInputDataTransformer
     *
     * @var ProjectInput[]
     * @Assert\All({
     *     @Assert\NotBlank,
     *     @Assert\Type(type=ProjectInput::class)
     * })
     * @Groups({"user:register"})
     */
    public array $createdProjects = [];

    /**
     * @var ProjectMembership[]
     *
     * @Assert\All({
     *     @Assert\NotBlank,
     *     @Assert\Type(type=ProjectMembership::class)
     * })
     * @Groups({"user:register"})
     */
    public array $projectMemberships = [];

    /**
     * @var string
     * @Assert\NotBlank(allowNull=false, groups={"user:changeEmail", "user:register", "user:resetPassword"})
     * @Assert\Regex(
     *     pattern="/{{token}}/",
     *     message="Token placeholder is missing."
     * )
     * @Assert\Regex(
     *     pattern="/{{id}}/",
     *     message="ID placeholder is missing."
     * )
     * @Groups({"user:changeEmail", "user:register", "user:resetPassword"})
     */
    public ?string $validationUrl = null;
}
