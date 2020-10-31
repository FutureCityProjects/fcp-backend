<?php
declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class ClimateImpactInput
{
    /**
     * @var string|null
     *
     * @Assert\Length(max=500, allowEmptyString=true)
     * @Groups({"project:write"})
     */
    public ?string $emissionAvoidance = null;

    /**
     * @var string|null
     *
     * @Assert\Length(max=500, allowEmptyString=true)
     * @Groups({"project:write"})
     */
    public ?string $changeAdaption = null;

    /**
     * @var string|null
     *
     * @Assert\Length(max=500, allowEmptyString=true)
     * @Groups({"project:write"})
     */
    public ?string $collaborationImplementation = null;

    /**
     * @var bool|null
     *
     * @Assert\Type(type="boolean")
     * @Groups({"project:write"})
     */
    public ?bool $resourceUsageReduction = null;

    /**
     * @var bool|null
     *
     * @Assert\Type(type="boolean")
     * @Groups({"project:write"})
     */
    public ?bool $usingRenewableResources = null;

    /**
     * @var bool|null
     *
     * @Assert\Type(type="boolean")
     * @Groups({"project:write"})
     */
    public ?bool $reducingFossilMobility = null;

    /**
     * @var bool|null
     *
     * @Assert\Type(type="boolean")
     * @Groups({"project:write"})
     */
    public ?bool $adaptingFoodSources = null;

    /**
     * @var bool|null
     *
     * @Assert\Type(type="boolean")
     * @Groups({"project:write"})
     */
    public ?bool $adaptingPostFossilFinances = null;

    /**
     * @var bool|null
     *
     * @Assert\Type(type="boolean")
     * @Groups({"project:write"})
     */
    public ?bool $preparingForExtremeWeather = null;

    /**
     * @var bool|null
     *
     * @Assert\Type(type="boolean")
     * @Groups({"project:write"})
     */
    public ?bool $adaptingResilientBusinessModels = null;

    /**
     * @var bool|null
     *
     * @Assert\Type(type="boolean")
     * @Groups({"project:write"})
     */
    public ?bool $supportingClimateRelevantPolitics = null;

    /**
     * @var bool|null
     *
     * @Assert\Type(type="boolean")
     * @Groups({"project:write"})
     */
    public ?bool $educatingClimateKnowledge = null;

    /**
     * @var bool|null
     *
     * @Assert\Type(type="boolean")
     * @Groups({"project:write"})
     */
    public ?bool $educatingClimateEmpowerment = null;

    /**
     * @var bool|null
     *
     * @Assert\Type(type="boolean")
     * @Groups({"project:write"})
     */
    public ?bool $supportingCollaboration = null;

    /**
     * @var bool|null
     *
     * @Assert\Type(type="boolean")
     * @Groups({"project:write"})
     */
    public ?bool $otherClimateImpacts = null;
}
