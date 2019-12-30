<?php
declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class UserInput
{
    /**
     * @var string
     * @Groups({"user:admin-write", "user:po-write"})
     */
    public ?string $username = null;

    /**
     * @var string
     * @Groups({"user:admin-write", "user:po-write"})
     */
    public ?string $email = null;

    /**
     * @var string
     * @Groups({"user:write"})
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
     * @var string
     * @Groups({"user:register"})
     * @Assert\NotBlank(groups={"user:register"}, allowNull=false)
     * @Assert\Regex(
     *     groups={"user:register"},
     *     pattern="/{{token}}/",
     *     message="Token placeholder is missing."
     * )
     * @Assert\Regex(
     *     groups={"user:register"},
     *     pattern="/{{id}}/",
     *     message="ID placeholder is missing."
     * )
     */
    public ?string $validationUrl = null;
}
