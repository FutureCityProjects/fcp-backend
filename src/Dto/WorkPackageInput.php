<?php
declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class WorkPackageInput
{
    /**
     * @var string|null
     *
     * @Assert\Length(max=500, allowEmptyString=true)
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
     * @var string|null
     *
     * @Assert\Length(max=100, allowEmptyString=true)
     * @Groups({"project:write"})
     */
    public ?string $mainResponsibility = null;

    /**
     * @var string|null
     *
     * @Assert\NotBlank(allowNull=false)
     * @Assert\Length(min=2, max=100, allowEmptyString=true)
     * @Groups({"project:write"})
     */
    public ?string $name = null;

    /**
     * @var int
     *
     * @Assert\Type(type="integer")
     * @Assert\Range(min="0", max="1000")
     * @Groups({"project:write"})
     */
    public ?int $order = null;
}
