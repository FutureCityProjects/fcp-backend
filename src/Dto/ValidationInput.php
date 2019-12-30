<?php
declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class ValidationInput
{
    /**
     * @var string
     * @Assert\NotBlank(allowNull=false)
     * @Assert\Regex(
     *     pattern="/^[A-Za-z0-9]*$/",
     *     message="Invalid token string."
     * )
     * @Groups({"validation:confirm"})
     */
    public ?string $token = null;
}
