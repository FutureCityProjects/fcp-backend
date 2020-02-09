<?php
declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class ResourceRequirementInput
{
    public const COST_TYPE_ADMINISTRATIVE = 'administrative';
    public const COST_TYPE_INVESTMENT = 'investment';
    public const COST_TYPE_MATERIAL = 'material';
    public const COST_TYPE_PERSONNEL = 'personnel';
    public const COST_TYPE_RENT = 'rent';
    public const COST_TYPE_TRAVELING = 'traveling';

    public const SOURCE_TYPE_FUNDING = 'funding';
    public const SOURCE_TYPE_OWN_FUNDS = 'own_funds';
    public const SOURCE_TYPE_PROCEEDS = 'proceeds';

    /**
     * @var int
     *
     * @Assert\Type(type="integer")
     * @Assert\Range(min="0", max="99999999")
     * @Groups({"project:write"})
     */
    public ?int $cost = null;

    /**
     * @var string|null
     *
     * @Assert\Choice(
     *     choices={
     *         ResourceRequirementInput::COST_TYPE_ADMINISTRATIVE,
     *         ResourceRequirementInput::COST_TYPE_INVESTMENT,
     *         ResourceRequirementInput::COST_TYPE_MATERIAL,
     *         ResourceRequirementInput::COST_TYPE_PERSONNEL,
     *         ResourceRequirementInput::COST_TYPE_RENT,
     *         ResourceRequirementInput::COST_TYPE_TRAVELING
     *     }
     * )
     * @Groups({"project:write"})
     */
    public ?string $costType = null;

    /**
     * @var string|null
     *
     * @Assert\NotBlank(allowNull=false)
     * @Assert\Length(min=5, max=280, allowEmptyString=true)
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
     * @Assert\Length(max=280, allowEmptyString=true)
     * @Groups({"project:write"})
     */
    public ?string $source = null;

    /**
     * @var string|null
     *
     * @Assert\Choice(
     *     choices={
     *         ResourceRequirementInput::SOURCE_TYPE_FUNDING,
     *         ResourceRequirementInput::SOURCE_TYPE_OWN_FUNDS,
     *         ResourceRequirementInput::SOURCE_TYPE_PROCEEDS,
     *     }
     * )
     * @Groups({"project:write"})
     */
    public ?string $sourceType = null;

    /**
     * @var string|null
     *
     * @Assert\NotBlank(allowNull=true)
     * @Assert\Length(min=6, max=200, allowEmptyString=true)
     * @Groups({"project:write"})
     */
    public ?string $task = null;
}
