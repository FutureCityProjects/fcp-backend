<?php
declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

class UserInput
{
    /**
     * @var string
     * @Groups({"user:write"})
     */
    public ?string $username = null;

    /**
     * @var string
     * @Groups({"user:write"})
     */
    public ?string $email = null;

    /**
     * @var string
     * @Groups({"user:write"})
     */
    public ?string $password = null;

    /**
     * @var array
     * @Groups({"user:write"})
     */
    public ?array $roles = null;

    /**
     * @var bool
     * @Groups({"user:write"})
     */
    public ?bool $isActive = null;

    /**
     * @var bool
     * @Groups({"user:write"})
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
}
