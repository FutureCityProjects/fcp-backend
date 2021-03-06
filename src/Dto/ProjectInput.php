<?php
declare(strict_types=1);

namespace App\Dto;

use App\Entity\Process;
use App\Entity\Project;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class ProjectInput
{
    /**
     * @var \App\Entity\Process
     * @Groups({"project:create", "user:register"})
     */
    public ?Process $process = null;

    /**
     * @Assert\Choice({Project::STATE_ACTIVE, Project::STATE_DEACTIVATED})
     * @Groups({"project:owner-update", "project:po-update", "project:admin-update"})
     */
    public ?string $state = null;

    /**
     * @Assert\Choice(
     *     choices={Project::PROGRESS_IDEA, Project::PROGRESS_CREATING_PROFILE},
     *     groups={"project:create", "user:register"}
     * )
     * @Groups({"project:create", "user:register"})
     */
    public ?string $progress = null;

    /**
     * @Groups({"project:po-write", "project:admin-write"})
     */
    public ?bool $isLocked = null;

    /**
     * @var \App\Entity\Project
     * @Groups({"project:create", "user:register"})
     */
    public ?Project $inspiration = null;

    //region Project profile
    /**
     * @Groups({"project:write"})
     */
    public ?string $challenges = null;

    /**
     * @Groups({"project:write"})
     */
    public ?string $delimitation = null;

    /**
     * @Groups({"project:write"})
     */
    public ?string $description = null;

    /**
     * @Groups({"project:write"})
     */
    public ?string $goal = null;

    /**
     * @Groups({"project:write"})
     */
    public ?string $name = null;

    /**
     * @Groups({"project:write"})
     */
    public ?int $profileSelfAssessment = null;

    /**
     * @Groups({"project:write", "user:register"})
     */
    public ?string $shortDescription = null;

    /**
     * @Groups({"project:write"})
     */
    public ?string $vision = null;
    //endregion

    //region Project creation
    /**
     * @var string
     * @Assert\NotBlank(allowNull=true, groups={"user:register"})
     * @Groups({"project:create", "user:register"})
     */
    public ?string $motivation = null;

    /**
     * @var string
     * @Assert\NotBlank(allowNull=true, groups={"user:register"})
     * @Groups({"project:create", "user:register"})
     */
    public ?string $skills = null;
    //endregion

    //region Project plan
    /**
     * @Groups({"project:write"})
     */
    public ?array $impact = null;

    /**
     * @var \DateTimeImmutable|null
     * @Groups({"project:write"})
     */
    public ?\DateTimeImmutable $implementationBegin = null;

    /**
     * @Groups({"project:write"})
     */
    public ?int $implementationTime = null;

    /**
     * @Groups({"project:write"})
     */
    public ?array $outcome = null;

    /**
     * @Groups({"project:write"})
     */
    public ?int $planSelfAssessment = null;

    /**
     * @var ResourceRequirementInput[]|null
     *
     * @Assert\Valid
     * @Groups({"project:write"})
     */
    public ?array $resourceRequirements = null;

    /**
     * @Groups({"project:write"})
     */
    public ?array $results = null;

    /**
     * @Groups({"project:write"})
     */
    public ?array $targetGroups = null;

    /**
     * @var ProjectTaskInput[]|null
     *
     * @Assert\Valid
     * @Groups({"project:write"})
     */
    public ?array $tasks = null;

    /**
     * @Groups({"project:write"})
     */
    public ?string $utilization = null;

    /**
     * @var WorkPackageInput[]|null
     *
     * @Assert\Valid
     * @Groups({"project:write"})
     */
    public ?array $workPackages = null;
    //endregion

    //region application data
    /**
     * @Groups({"project:write"})
     */
    public ?string $contactEmail = null;

    /**
     * @Groups({"project:write"})
     */
    public ?string $contactName = null;

    /**
     * @Groups({"project:write"})
     */
    public ?string $contactPhone = null;

    /**
     * @Groups({"project:write"})
     */
    public ?string $holderAddressInfo = null;

    /**
     * @Groups({"project:write"})
     */
    public ?string $holderCity = null;

    /**
     * @Groups({"project:write"})
     */
    public ?string $holderName = null;

    /**
     * @Groups({"project:write"})
     */
    public ?string $holderStreet = null;

    /**
     * @Groups({"project:write"})
     */
    public ?string $holderZipCode = null;
    //endregion
}
