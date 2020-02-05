<?php
declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use App\Entity\Helper\ProjectHelper;
use App\Entity\Traits\AutoincrementId;
use App\Entity\Traits\CreatedAtFunctions;
use App\Entity\Traits\DeletedAtFunctions;
use App\Entity\Traits\NameFunctions;
use App\Entity\Traits\UpdatedAtFunctions;
use App\Entity\UploadedFileTypes\ProjectPicture;
use App\Entity\UploadedFileTypes\ProjectVisualization;
use App\Validator\Constraints as AppAssert;
use App\Validator\NormalizerHelper;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Project
 *
 * @ApiResource(
 *     attributes={"security"="is_granted('IS_AUTHENTICATED_ANONYMOUSLY')"},
 *     collectionOperations={
 *         "get",
 *         "post"={
 *             "security"="is_granted('ROLE_USER')",
 *             "validation_groups"={"Default", "project:create"}
 *         }
 *     },
 *     itemOperations={
 *         "get",
 *         "put"={
 *             "security"="is_granted('EDIT', object)",
 *             "validation_groups"={"Default", "project:write"}
 *         },
 *         "delete"={"security"="is_granted('DELETE', object)"}
 *     },
 *     input="App\Dto\ProjectInput",
 *     normalizationContext={
 *         "groups"={"default:read", "project:read"},
 *         "enable_max_depth"=true,
 *         "swagger_definition_name"="Read"
 *     },
 *     denormalizationContext={
 *         "groups"={"default:write", "project:write"},
 *         "swagger_definition_name"="Write"
 *     }
 * )
 * @ApiFilter(BooleanFilter::class, properties={"isLocked"})
 * @ApiFilter(ExistsFilter::class, properties={"deletedAt"})
 * @ApiFilter(SearchFilter::class, properties={
 *     "id": "exact",
 *     "progress": "exact",
 *     "slug": "exact",
 *     "state": "exact"
 * })
 * @AppAssert\UnmodifiedIdea(groups={"project:write"})
 * @Assert\Callback(
 *     groups={"project:create", "user:register"},
 *     callback={"App\Validator\ProjectValidator", "validateCreation"}
 * )
 * @Assert\Callback(
 *     groups={"project:write"},
 *     callback={"App\Validator\ProjectValidator", "validateUpdate"}
 * )
 *
 * @ORM\Entity
 * @ORM\EntityListeners({"App\Entity\Listener\ProjectListener"})
 * @ORM\Table(indexes={
 *     @ORM\Index(name="state_idx", columns={"state"})
 * }, uniqueConstraints={
 *     @ORM\UniqueConstraint(name="name_process", columns={"name", "process_id"})
 * })
 */
class Project
{
    public const STATE_ACTIVE = 'active';
    public const STATE_INACTIVE = 'inactive';
    public const STATE_DEACTIVATED = 'deactivated';

    public const PROGRESS_IDEA = 'idea';
    public const PROGRESS_CREATING_PROFILE = 'creating_profile';
    public const PROGRESS_CREATING_PLAN = 'creating_plan';
    public const PROGRESS_CREATING_APPLICATION = 'creating_application';
    public const PROGRESS_SUBMITTING_APPLICATION = 'submitting_application';
    public const PROGRESS_APPLICATION_SUBMITTED = 'application_submitted';

    public const SELF_ASSESSMENT_0_PERCENT   = 0;
    public const SELF_ASSESSMENT_25_PERCENT  = 25;
    public const SELF_ASSESSMENT_50_PERCENT  = 50;
    public const SELF_ASSESSMENT_75_PERCENT  = 75;
    public const SELF_ASSESSMENT_100_PERCENT = 100;

    use AutoincrementId;

    //region Applications
    /**
     * @Groups({
     *     "project:owner-read",
     *     "project:member-read",
     *     "project:po-read",
     *     "project:admin-read"
     * })
     * @MaxDepth(2)
     * @ORM\OneToMany(targetEntity="FundApplication", mappedBy="project", orphanRemoval=true)
     */
    private $applications;

    /**
     * @return Collection|FundApplication[]
     */
    public function getApplications(): Collection
    {
        return $this->applications;
    }

    public function addApplication(FundApplication $application): self
    {
        if (!$this->applications->contains($application)) {
            $this->applications[] = $application;
            $application->setProject($this);
        }

        return $this;
    }

    public function removeApplication(FundApplication $application): self
    {
        if ($this->applications->contains($application)) {
            $this->applications->removeElement($application);
            // set the owning side to null (unless already changed)
            if ($application->getProject() === $this) {
                $application->setProject(null);
            }
        }

        return $this;
    }
    //endregion

    //region Challenges
    /**
     * @var string
     *
     * @Assert\Length(min=10, max=2000, allowEmptyString=true,
     *     normalizer={NormalizerHelper::class, "stripHtml"}
     * )
     * @Groups({"elastica", "project:read", "project:write"})
     * @ORM\Column(type="text", length=6000, nullable=true)
     */
    private ?string $challenges = null;

    public function getChallenges(): ?string
    {
        return $this->challenges;
    }

    public function setChallenges(?string $challenges): self
    {
        if (NormalizerHelper::getTextLength($challenges) === 0) {
            $this->challenges = null;
        } else {
            $this->challenges = trim($challenges);
        }

        return $this;
    }
    //endregion

    //region ContactEmail
    /**
     * @var string
     *
     * @Assert\Email
     * @Groups({"elastica", "project:read", "project:write"})
     * @ORM\Column(type="text", length=255, nullable=true)
     */
    private ?string $contactEmail = null;

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function setContactEmail(?string $contactEmail): self
    {
        $this->contactEmail = $contactEmail;

        return $this;
    }
    //endregion
    
    //region ContactName
    /**
     * @var string
     *
     * @Groups({"elastica", "project:read", "project:write"})
     * @ORM\Column(type="text", length=100, nullable=true)
     */
    private ?string $contactName = null;

    public function getContactName(): ?string
    {
        return $this->contactName;
    }

    public function setContactName(?string $contactName): self
    {
        if (NormalizerHelper::getTextLength($contactName) === 0) {
            $this->contactName = null;
        } else {
            $this->contactName = trim($contactName);
        }

        return $this;
    }
    //endregion

    //region ContactPhone
    /**
     * @var string
     *
     * @Groups({"elastica", "project:read", "project:write"})
     * @ORM\Column(type="text", length=100, nullable=true)
     */
    private ?string $contactPhone = null;

    public function getContactPhone(): ?string
    {
        return $this->contactPhone;
    }

    public function setContactPhone(?string $contactPhone): self
    {
        if (NormalizerHelper::getTextLength($contactPhone) === 0) {
            $this->contactPhone = null;
        } else {
            $this->contactPhone = trim($contactPhone);
        }

        return $this;
    }
    //endregion

    //region CreatedAt
    /**
     * @var DateTimeImmutable
     *
     * @Assert\NotBlank(allowNull=true)
     * @Groups({"project:read"})
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime_immutable")
     */
    protected ?DateTimeImmutable $createdAt = null;

    use CreatedAtFunctions;
    //endregion

    //region CreatedBy
    /**
     * @var User
     * @Groups({
     *     "project:create",
     *     "project:read"
     * })
     * @MaxDepth(1)
     * @ORM\ManyToOne(targetEntity="User", inversedBy="createdProjects")
     * @ORM\JoinColumn(nullable=true, onDelete="SET NULL")
     */
    private ?User $createdBy = null;

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }
    //endregion

    //region DeletedAt
    /**
     * @var DateTimeImmutable
     * @Groups({"project:admin-read"})
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    protected ?DateTimeImmutable $deletedAt = null;

    use DeletedAtFunctions;
    //endregion

    //region Delimitation
    /**
     * @var string
     *
     * @Assert\Length(min=10, max=2000, allowEmptyString=true,
     *     normalizer={NormalizerHelper::class, "stripHtml"}
     * )
     * @Groups({"elastica", "project:read", "project:write"})
     * @ORM\Column(type="text", length=6000, nullable=true)
     */
    private ?string $delimitation = null;

    public function getDelimitation(): ?string
    {
        return $this->delimitation;
    }

    public function setDelimitation(?string $delimitation): self
    {
        if (NormalizerHelper::getTextLength($delimitation) === 0) {
            $this->delimitation = null;
        } else {
            $this->delimitation = trim($delimitation);
        }

        return $this;
    }
    //endregion

    //region Description
    /**
     * @var string
     *
     * @Assert\Length(max=2000, allowEmptyString=true,
     *     normalizer={NormalizerHelper::class, "stripHtml"}
     * )
     * @Groups({"elastica", "project:read", "project:write"})
     * @ORM\Column(type="text", length=6000, nullable=true)
     */
    private ?string $description = null;

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        if (NormalizerHelper::getTextLength($description) === 0) {
            $this->description = null;
        } else {
            $this->description = trim($description);
        }

        return $this;
    }
    //endregion

    //region Goal
    /**
     * @var string
     *
     * @Assert\Length(min=10, max=1000, allowEmptyString=true,
     *     normalizer={NormalizerHelper::class, "stripHtml"}
     * )
     * @Groups({"elastica", "project:read", "project:write"})
     * @ORM\Column(type="text", length=3000, nullable=true)
     */
    private ?string $goal = null;

    public function getGoal(): ?string
    {
        return $this->goal;
    }

    public function setGoal(?string $goal): self
    {
        if (NormalizerHelper::getTextLength($goal) === 0) {
            $this->goal = null;
        } else {
            $this->goal = trim($goal);
        }

        return $this;
    }
    //endregion

    //region HolderAddressInfo
    /**
     * @var string
     *
     * @Groups({"elastica", "project:read", "project:write"})
     * @ORM\Column(type="text", length=100, nullable=true)
     */
    private ?string $holderAddressInfo = null;

    public function getHolderAddressInfo(): ?string
    {
        return $this->holderAddressInfo;
    }

    public function setHolderAddressInfo(?string $holderAddressInfo): self
    {
        if (NormalizerHelper::getTextLength($holderAddressInfo) === 0) {
            $this->holderAddressInfo = null;
        } else {
            $this->holderAddressInfo = trim($holderAddressInfo);
        }

        return $this;
    }
    //endregion

    //region HolderCity
    /**
     * @var string
     *
     * @Groups({"elastica", "project:read", "project:write"})
     * @ORM\Column(type="text", length=100, nullable=true)
     */
    private ?string $holderCity = null;

    public function getHolderCity(): ?string
    {
        return $this->holderCity;
    }

    public function setHolderCity(?string $holderCity): self
    {
        if (NormalizerHelper::getTextLength($holderCity) === 0) {
            $this->holderCity = null;
        } else {
            $this->holderCity = trim($holderCity);
        }

        return $this;
    }
    //endregion

    //region HolderName
    /**
     * @var string
     *
     * @Groups({"elastica", "project:read", "project:write"})
     * @ORM\Column(type="text", length=200, nullable=true)
     */
    private ?string $holderName = null;

    public function getHolderName(): ?string
    {
        return $this->holderName;
    }

    public function setHolderName(?string $holderName): self
    {
        if (NormalizerHelper::getTextLength($holderName) === 0) {
            $this->holderName = null;
        } else {
            $this->holderName = trim($holderName);
        }

        return $this;
    }
    //endregion

    //region HolderStreet
    /**
     * @var string
     *
     * @Groups({"elastica", "project:read", "project:write"})
     * @ORM\Column(type="text", length=100, nullable=true)
     */
    private ?string $holderStreet = null;

    public function getHolderStreet(): ?string
    {
        return $this->holderStreet;
    }

    public function setHolderStreet(?string $holderStreet): self
    {
        if (NormalizerHelper::getTextLength($holderStreet) === 0) {
            $this->holderStreet = null;
        } else {
            $this->holderStreet = trim($holderStreet);
        }

        return $this;
    }
    //endregion

    //region HolderZipCode
    /**
     * @var string
     *
     * @Groups({"elastica", "project:read", "project:write"})
     * @ORM\Column(type="text", length=10, nullable=true)
     */
    private ?string $holderZipCode = null;

    public function getHolderZipCode(): ?string
    {
        return $this->holderZipCode;
    }

    public function setHolderZipCode(?string $holderZipCode): self
    {
        if (NormalizerHelper::getTextLength($holderZipCode) === 0) {
            $this->holderZipCode = null;
        } else {
            $this->holderZipCode = trim($holderZipCode);
        }

        return $this;
    }
    //endregion

    //region Impact
    /**
     * @var array
     *
     * @Assert\All({
     *     @Assert\NotBlank(allowNull=false, normalizer="trim"),
     *     @Assert\Length(min=5, max=200, allowEmptyString=true,
     *         normalizer="trim"
     *     )
     * })
     * @Assert\NotBlank(allowNull=true)
     * @Groups({
     *     "project:owner-read",
     *     "project:member-read",
     *     "project:po-read",
     *     "project:admin-read",
     *     "project:write",
     * })
     * @ORM\Column(type="json", nullable=true)
     */
    private ?array $impact = null;

    public function getImpact(): ?array
    {
        return $this->impact;
    }

    public function setImpact(array $impact): self
    {
        $this->impact = is_array($impact) && count($impact)
            ? $impact
            : null;

        return $this;
    }
    //endregion

    //region ImplementationTime
    /**
     * @var int
     *
     * @Assert\Range(min="0", max="120")
     * @Groups({
     *     "project:owner-read",
     *     "project:member-read",
     *     "project:po-read",
     *     "project:admin-read",
     *     "project:write",
     * })})
     * @ORM\Column(type="smallint", nullable=true, options={"unsigned":true})
     */
    private ?int $implementationTime = null;

    public function getImplementationTime(): ?int
    {
        return $this->implementationTime;
    }

    public function setImplementationTime(?int $implementationTime): self
    {
        $this->implementationTime = $implementationTime;

        return $this;
    }
    //endregion

    //region Inspiration
    /**
     * @var Project
     * @Groups({"project:read", "project:create", "user:register"})
     * @MaxDepth(1)
     * @ORM\ManyToOne(targetEntity="Project", fetch="EXTRA_LAZY", inversedBy="resultingProjects")
     * @ORM\JoinColumn(nullable=true, onDelete="SET NULL")
     */
    private ?self $inspiration = null;

    public function getInspiration(): ?self
    {
        return $this->inspiration;
    }

    public function setInspiration(?self $inspiration): self
    {
        $this->inspiration = $inspiration;

        return $this;
    }
    //endregion

    //region IsLocked
    /**
     * @Groups({
     *     "project:po-read",
     *     "project:admin-read",
     *     "project:po-write",
     *     "project:admin-write"
     * })
     * @ORM\Column(type="boolean", options={"default":false})
     */
    private $isLocked = false;

    public function isLocked(): bool
    {
        return $this->getIsLocked();
    }

    public function getIsLocked(): bool
    {
        return $this->isLocked;
    }

    public function setIsLocked(bool $isLocked = true): self
    {
        $this->isLocked = $isLocked;

        return $this;
    }
    //endregion

    //region Memberships
    /**
     * @var Collection|ProjectMembership[]
     * @Groups({
     *     "project:owner-read",
     *     "project:member-read",
     *     "project:po-read",
     *     "project:admin-read",
     *     "user:register"
     * })
     * @MaxDepth(2)
     * @ORM\OneToMany(
     *     targetEntity="ProjectMembership",
     *     mappedBy="project",
     *     cascade={"persist", "remove"},
     *     orphanRemoval=true
     * )
     */
    private $memberships;

    /**
     * @return Collection|ProjectMembership[]
     */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    public function addMembership(ProjectMembership $membership): self
    {
        if (!$this->memberships->contains($membership)) {
            $this->memberships[] = $membership;
            $membership->setProject($this);
        }

        return $this;
    }

    public function removeMembership(ProjectMembership $membership): self
    {
        if ($this->memberships->contains($membership)) {
            $this->memberships->removeElement($membership);
            // set the owning side to null (unless already changed)
            if ($membership->getProject() === $this) {
                $membership->setProject(null);
            }
        }

        return $this;
    }
    //endregion

    //region Name
    /**
     * @var string
     *
     * @Assert\Length(min=5, max=100, allowEmptyString=true, normalizer="trim")
     * @Groups({"elastica", "project:read", "project:write", "user:read"})
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private ?string $name = null;

    use NameFunctions;
    //endregion

    //region Outcome
    /**
     * @var array
     *
     * @Assert\All({
     *     @Assert\NotBlank(allowNull=false, normalizer="trim"),
     *     @Assert\Length(min=5, max=200, allowEmptyString=true,
     *         normalizer="trim"
     *     )
     * })
     * @Assert\NotBlank(allowNull=true)
     * @Groups({
     *     "project:owner-read",
     *     "project:member-read",
     *     "project:po-read",
     *     "project:admin-read",
     *     "project:write",
     * })
     * @ORM\Column(type="json", nullable=true)
     */
    private ?array $outcome = null;

    public function getOutcome(): ?array
    {
        return $this->outcome;
    }

    public function setOutcome(array $outcome): self
    {
        $this->outcome = is_array($outcome) && count($outcome)
            ? $outcome
            : null;

        return $this;
    }
    //endregion

    //region Picture
    /**
     * @var ProjectPicture
     * @Groups({"project:read", "project:write"})
     * @ORM\ManyToOne(targetEntity="App\Entity\UploadedFileTypes\ProjectPicture")
     * @ORM\JoinColumn(nullable=true)
     */
    private $picture;

    public function getPicture(): ?ProjectPicture
    {
        return $this->picture;
    }

    public function setPicture(?ProjectPicture $picture): self
    {
        $this->picture = $picture;

        return $this;
    }
    //endregion

    //region ProfileSelfAssessment
    /**
     * @var int
     * @Assert\Choice(
     *     choices={
     *         Project::SELF_ASSESSMENT_0_PERCENT,
     *         Project::SELF_ASSESSMENT_25_PERCENT,
     *         Project::SELF_ASSESSMENT_50_PERCENT,
     *         Project::SELF_ASSESSMENT_75_PERCENT,
     *         Project::SELF_ASSESSMENT_100_PERCENT
     *     }
     * )
     * @Groups({
     *     "project:owner-read",
     *     "project:member-read",
     *     "project:po-read",
     *     "project:admin-read",
     *     "project:write",
     * })})
     * @ORM\Column(type="smallint", nullable=false, options={"unsigned":true})
     */
    private int $profileSelfAssessment = self::SELF_ASSESSMENT_0_PERCENT;

    public function getProfileSelfAssessment(): int
    {
        return $this->profileSelfAssessment;
    }

    public function setProfileSelfAssessment(int $profileSelfAssessment): self
    {
        $this->profileSelfAssessment = $profileSelfAssessment;

        return $this;
    }
    //endregion

    //region Process
    /**
     * @var Process
     * @Groups({"project:read", "project:create", "user:register"})
     * @MaxDepth(1)
     * @ORM\ManyToOne(targetEntity="Process", inversedBy="projects", fetch="EXTRA_LAZY")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Process $process = null;

    public function getProcess(): ?Process
    {
        return $this->process;
    }

    public function setProcess(Process $process): self
    {
        $this->process = $process;

        return $this;
    }
    //endregion

    //region PlanSelfAssessment
    /**
     * @var int
     * @Assert\Choice(
     *     choices={
     *         Project::SELF_ASSESSMENT_0_PERCENT,
     *         Project::SELF_ASSESSMENT_25_PERCENT,
     *         Project::SELF_ASSESSMENT_50_PERCENT,
     *         Project::SELF_ASSESSMENT_75_PERCENT,
     *         Project::SELF_ASSESSMENT_100_PERCENT
     *     }
     * )
     * @Groups({
     *     "project:owner-read",
     *     "project:member-read",
     *     "project:po-read",
     *     "project:admin-read",
     *     "project:write",
     * })})
     * @ORM\Column(type="smallint", nullable=false, options={"unsigned":true})
     */
    private int $planSelfAssessment = self::SELF_ASSESSMENT_0_PERCENT;

    public function getPlanSelfAssessment(): int
    {
        return $this->planSelfAssessment;
    }

    public function setPlanSelfAssessment(int $planSelfAssessment): self
    {
        $this->planSelfAssessment = $planSelfAssessment;

        return $this;
    }
    //endregion

    //region Progress
    /**
     * @var string
     * @Assert\Choice(
     *     choices={
     *         Project::PROGRESS_IDEA,
     *         Project::PROGRESS_CREATING_PROFILE,
     *         Project::PROGRESS_CREATING_PLAN,
     *         Project::PROGRESS_CREATING_APPLICATION,
     *         Project::PROGRESS_APPLICATION_SUBMITTED
     *     }
     * )
     * @Groups({"project:read", "user:register", "user:self"})
     * @ORM\Column(type="string", length=50, nullable=false, options={"default":"idea"})
     */
    private ?string $progress = null;

    public function getProgress(): ?string
    {
        return $this->progress;
    }

    public function setProgress(string $progress): self
    {
        $this->progress = $progress;

        return $this;
    }
    //endregion

    //region Results
    /**
     * @var array
     *
     * @Assert\All({
     *     @Assert\NotBlank(allowNull=false, normalizer="trim"),
     *     @Assert\Length(min=5, max=200, allowEmptyString=true,
     *         normalizer="trim"
     *     )
     * })
     * @Assert\NotBlank(allowNull=true)
     * @Groups({
     *     "project:owner-read",
     *     "project:member-read",
     *     "project:po-read",
     *     "project:admin-read",
     *     "project:write",
     * })
     * @ORM\Column(type="json", nullable=true)
     */
    private ?array $results = null;

    public function getResults(): ?array
    {
        return $this->results;
    }

    public function setResults(array $results): self
    {
        $this->results = is_array($results) && count($results)
            ? $results
            : null;

        return $this;
    }
    //endregion

    //region ResultingProjects
    /**
     * @Groups({"project:read"})
     * @ORM\OneToMany(targetEntity="Project", mappedBy="inspiration")
     */
    private $resultingProjects;

    /**
     * @return Collection|Project[]
     */
    public function getResultingProjects(): Collection
    {
        return $this->resultingProjects;
    }
    //endregion

    //region ShortDescription
    /**
     * @var string
     * @Groups({"elastica", "project:read", "project:write", "user:register"})
     * @Assert\Length(min=10, max=280, allowEmptyString=false)
     * @ORM\Column(type="string", length=280, nullable=false)
     */
    private ?string $shortDescription = null;

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(string $shortDescription): self
    {
        $this->shortDescription = trim($shortDescription);

        return $this;
    }
    //endregion

    //region Slug
    /**
     * @var string
     * @Groups({"elastica", "project:read"})
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Gedmo\Slug(fields={"name"})
     */
    private ?string $slug = null;

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }
    //endregion

    //region State
    /**
     * @var string
     * @Assert\Choice(
     *     choices={
     *         Project::STATE_ACTIVE,
     *         Project::STATE_DEACTIVATED,
     *         Project::STATE_INACTIVE,
     *     }
     * )
     * @Groups({
     *     "project:read",
     *     "project:owner-update",
     *     "project:po-update",
     *     "project:admin-update"
     * })
     * @ORM\Column(type="string", length=50, nullable=false, options={"default":"active"})
     */
    private string $state = self::STATE_ACTIVE;

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): self
    {
        $this->state = $state;

        return $this;
    }
    //endregion

    //region TargetGroups
    /**
     * @var array
     *
     * @Assert\All({
     *     @Assert\NotBlank(allowNull=false, normalizer="trim"),
     *     @Assert\Length(min=5, max=500, allowEmptyString=true,
     *         normalizer="trim"
     *     )
     * })
     * @Assert\NotBlank(allowNull=true)
     * @Groups({
     *     "project:owner-read",
     *     "project:member-read",
     *     "project:po-read",
     *     "project:admin-read",
     *     "project:write",
     * })
     * @ORM\Column(type="json", nullable=true)
     */
    private ?array $targetGroups = null;

    public function getTargetGroups(): ?array
    {
        return $this->targetGroups;
    }

    public function setTargetGroups(array $targetGroups): self
    {
        $this->targetGroups = is_array($targetGroups) && count($targetGroups)
            ? $targetGroups
            : null;

        return $this;
    }
    //endregion

    //region Tasks
    /**
     * @var array
     *
     * @Assert\All({
     *     @Assert\NotBlank(allowNull=false, normalizer="trim")
     * })
     * @Assert\NotBlank(allowNull=true)
     * @Assert\Callback(
     *     callback={"App\Validator\ProjectValidator", "validateTasks"}
     * )
     * @Groups({
     *     "project:owner-read",
     *     "project:member-read",
     *     "project:po-read",
     *     "project:admin-read",
     *     "project:write",
     * })
     * @ORM\Column(type="json", nullable=true)
     */
    private ?array $tasks = null;

    public function getTasks(): ?array
    {
        return $this->tasks;
    }

    public function setTasks(array $tasks): self
    {
        $this->tasks = is_array($tasks) && count($tasks)
            ? $tasks
            : null;

        return $this;
    }
    //endregion

    //region UpdatedAt
    /**
     * @var DateTimeImmutable
     *
     * @Assert\NotBlank(allowNull=true)
     * @Groups({"project:read"})
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime_immutable")
     */
    protected ?DateTimeImmutable $updatedAt = null;

    use UpdatedAtFunctions;
    //endregion
    
    //region Utilization
    /**
     * @var string
     *
     * @Assert\Length(min=20, max=2000, allowEmptyString=true,
     *     normalizer={NormalizerHelper::class, "stripHtml"}
     * )
     * @Groups({
     *     "project:owner-read",
     *     "project:member-read",
     *     "project:po-read",
     *     "project:admin-read",
     *     "project:write",
     * })
     * @ORM\Column(type="text", length=6000, nullable=true)
     */
    private ?string $utilization = null;

    public function getUtilization(): ?string
    {
        return $this->utilization;
    }

    public function setUtilization(?string $utilization): self
    {
        if (NormalizerHelper::getTextLength($utilization) === 0) {
            $this->utilization = null;
        } else {
            $this->utilization = trim($utilization);
        }

        return $this;
    }
    //endregion

    //region Vision
    /**
     * @var string
     *
     * @Assert\Length(min=10, max=2000, allowEmptyString=true,
     *     normalizer={NormalizerHelper::class, "stripHtml"}
     * )
     * @Groups({"elastica", "project:read", "project:write"})
     * @ORM\Column(type="text", length=6000, nullable=true)
     */
    private ?string $vision = null;

    public function getVision(): ?string
    {
        return $this->vision;
    }

    public function setVision(?string $vision): self
    {
        if (NormalizerHelper::getTextLength($vision) === 0) {
            $this->vision = null;
        } else {
            $this->vision = trim($vision);
        }

        return $this;
    }
    //endregion

    //region Visualization
    /**
     * @var ProjectVisualization
     * @Groups({"project:read", "project:write"})
     * @ORM\ManyToOne(targetEntity="App\Entity\UploadedFileTypes\ProjectVisualization")
     * @ORM\JoinColumn(nullable=true)
     */
    private $visualization;

    public function getVisualization(): ?ProjectVisualization
    {
        return $this->visualization;
    }

    public function setVisualization(?ProjectVisualization $visualization): self
    {
        $this->visualization = $visualization;

        return $this;
    }
    //endregion

    //region WorkPackages
    /**
     * @var array
     *
     * @Assert\All({
     *     @Assert\NotBlank(allowNull=false, normalizer="trim")
     * })
     * @Assert\NotBlank(allowNull=true)
     * @Assert\Callback(
     *     callback={"App\Validator\ProjectValidator", "validateWorkPackages"}
     * )
     * @Groups({
     *     "project:owner-read",
     *     "project:member-read",
     *     "project:po-read",
     *     "project:admin-read",
     *     "project:write",
     * })
     * @ORM\Column(type="json", nullable=true)
     */
    private ?array $workPackages = null;

    public function getWorkPackages(): ?array
    {
        return $this->workPackages;
    }

    public function setWorkPackages(array $workPackages): self
    {
        $this->workPackages = is_array($workPackages) && count($workPackages)
            ? $workPackages
            : null;

        return $this;
    }
    //endregion
    
    public function __construct()
    {
        $this->applications = new ArrayCollection();
        $this->memberships = new ArrayCollection();
        $this->resultingProjects = new ArrayCollection();
    }

    /**
     * Returns true when the given User is a member of this project, else false.
     * The project owner is also a member.
     *
     * @param UserInterface $user
     * @return bool
     */
    public function userIsMember(UserInterface $user)
    {
        foreach($this->getMemberships() as $membership) {
            if ($membership->getRole() !== ProjectMembership::ROLE_OWNER
                && $membership->getRole() !== ProjectMembership::ROLE_MEMBER
            ) {
                continue;
            }

            if ($membership->getUser()->getId() === $user->getId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns true when the given User is the owner of this project, else false.
     *
     * @param UserInterface $user
     * @return bool
     */
    public function userIsOwner(UserInterface $user)
    {
        foreach($this->getMemberships() as $membership) {
            if ($membership->getRole() !== ProjectMembership::ROLE_OWNER) {
                continue;
            }

            if ($membership->getUser()->getId() === $user->getId()) {
                return true;
            }
        }

        return false;
    }

    public function recalculateProgress()
    {
        if ($this->getProgress() === Project::PROGRESS_IDEA) {
            return;
        }

        $helper = new ProjectHelper($this);

        if (!$helper->isPlanAvailable()) {
            $this->setProgress(Project::PROGRESS_CREATING_PROFILE);
            return;
        }

        if (!$helper->isApplicationAvailable()) {
            $this->setProgress(Project::PROGRESS_CREATING_PLAN);
            return;
        }

        if (!$helper->isSubmissionAvailable()) {
            $this->setProgress(Project::PROGRESS_CREATING_APPLICATION);
            return;
        }

        // @todo when should the submitted-state reset? when a new fund is selected?
        if (!$helper->isApplicationSubmitted()) {
            $this->setProgress(Project::PROGRESS_SUBMITTING_APPLICATION);
            return;
        }

        $this->setProgress(Project::PROGRESS_APPLICATION_SUBMITTED);
    }
}
