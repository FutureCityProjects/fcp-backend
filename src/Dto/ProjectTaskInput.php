<?php
declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class ProjectTaskInput
{
    /**
     * @var string|null
     *
     * @Assert\NotBlank(allowNull=false)
     * @Assert\Length(min=6, max=200, allowEmptyString=true)
     * @Groups({"project:write"})
     */
    public ?string $description = null;

    /**
     * @var string|null
     *
     * @Assert\NotBlank(allowNull=false)
     * @Assert\Length(min=6, max=200, allowEmptyString=true)
     * @Groups({"project:write"})
     */
    public ?string $id = null;

    /**
     * @var int[]
     *
     * @Assert\All({
     *     @Assert\NotBlank(allowNull=false),
     *     @Assert\Type(type="integer"),
     *     @Assert\Range(min="1", max="120"),
     * })
     * @Groups({"project:write"})
     */
    public array $months = [];

    /**
     * @var string|null
     *
     * @Assert\NotBlank(allowNull=true)
     * @Assert\Length(min=4, max=200, allowEmptyString=true)
     * @Groups({"project:write"})
     */
    public ?string $result = null;

    /**
     * @var string|null
     *
     * @Assert\NotBlank(allowNull=true)
     * @Assert\Length(min=4, max=200, allowEmptyString=true)
     * @Groups({"project:write"})
     */
    public ?string $title = null;

    /**
     * @var string|null
     *
     * @Assert\NotBlank(allowNull=true)
     * @Assert\Length(min=6, max=200, allowEmptyString=true)
     * @Groups({"project:write"})
     */
    public ?string $workPackage = null;
}
