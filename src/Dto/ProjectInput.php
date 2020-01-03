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
     * @var \App\Entity\Project
     * @Groups({"project:create"})
     */
    public ?Project $inspiration = null;

    /**
     * @Groups({"project:po-write", "project:admin-write"})
     */
    public ?bool $isLocked = null;

    /**
     * @Groups({"project:write"})
     */
    public ?string $name = null;

    /**
     * @var \App\Entity\Process
     * @Groups({"project:create"})
     */
    public ?Process $process = null;

    /**
     * @Groups({"project:write"})
     */
    public ?int $profileSelfAssessment = null;

    /**
     * @Groups({"project:create"})
     */
    public ?string $progress = null;

    /**
     * @Groups({"project:write"})
     */
    public ?string $shortDescription = null;

    /**
     * @Assert\Choice({Project::STATE_ACTIVE, Project::STATE_DEACTIVATED})
     * @Groups({"project:owner-update", "project:po-update", "project:admin-update"})
     */
    public ?string $state = null;

    /**
     * @Groups({"project:write"})
     */
    public ?string $goal = null;

    /**
     * @Groups({"project:write"})
     */
    public ?string $vision = null;
}
