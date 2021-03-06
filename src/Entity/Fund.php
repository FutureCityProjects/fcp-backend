<?php
declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Controller\ActivateFundAction;
use App\Entity\Traits\NameFunctions;
use App\Entity\Traits\NameSlug;
use App\Entity\UploadedFileTypes\FundLogo;
use App\Validator\NormalizerHelper;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Fund
 *
 * @ApiResource(
 *     attributes={"security"="is_granted('IS_AUTHENTICATED_ANONYMOUSLY')"},
 *     collectionOperations={
 *         "get",
 *         "post"={"security"="is_granted('ROLE_PROCESS_OWNER')"}
 *     },
 *     itemOperations={
 *         "get",
 *         "put"={
 *             "security"="is_granted('ROLE_PROCESS_OWNER')"
 *         },
 *         "delete"={
 *             "security"="is_granted('DELETE', object)"
 *         },
 *         "activate"={
 *             "controller"=ActivateFundAction::class,
 *             "method"="POST",
 *             "path"="/funds/{id}/activate",
 *             "security"="is_granted('ROLE_PROCESS_OWNER')",
 *             "validation_groups"={"Default", "fund:activate"}
 *         }
 *     },
 *     normalizationContext={
 *         "groups"={"default:read", "fund:read"},
 *         "enable_max_depth"=true,
 *         "swagger_definition_name"="Read"
 *     },
 *     denormalizationContext={
 *         "groups"={"default:write", "fund:write"},
 *         "swagger_definition_name"="Write"
 *     }
 * )
 *
 * @ORM\Entity
 * @ORM\EntityListeners({"App\Entity\Listener\FundListener"})
 * @ORM\Table(indexes={
 *     @ORM\Index(name="state_idx", columns={"state"})
 * }, uniqueConstraints={
 *     @ORM\UniqueConstraint(name="name_process", columns={"name", "process_id"})
 * })
 * @UniqueEntity(fields={"name", "process"}, message="Name already exists.")
 */
class Fund
{
    const STATE_INACTIVE = 'inactive';
    const STATE_ACTIVE   = 'active';
    const STATE_FINISHED = 'finished';

    use NameSlug;

    //region Applications
    /**
     * @var Collection|FundApplication[]
     *
     * No group annotation, applications should be fetched directly.
     * @ORM\OneToMany(targetEntity="FundApplication", mappedBy="fund", orphanRemoval=true)
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
            $application->setFund($this);
        }

        return $this;
    }

    public function removeApplication(FundApplication $application): self
    {
        if ($this->applications->contains($application)) {
            $this->applications->removeElement($application);
            // set the owning side to null (unless already changed)
            if ($application->getFund() === $this) {
                $application->setFund(null);
            }
        }

        return $this;
    }
    //endregion

    //region BriefingDate
    /**
     * @var DateTimeImmutable|null
     *
     * @Groups({"fund:po-read", "fund:write", "fund:juror-read"})
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    private $briefingDate;

    public function getBriefingDate(): ?DateTimeImmutable
    {
        return $this->briefingDate;
    }

    public function setBriefingDate(?DateTimeImmutable $briefingDate): self
    {
        $this->briefingDate = $briefingDate;

        return $this;
    }
    //endregion

    //region Budget
    /**
     * @var float|null
     *
     * @Groups({"fund:read", "fund:write"})
     * @ORM\Column(type="float", precision=10, scale=0, nullable=true)
     */
    private $budget;

    public function getBudget(): ?float
    {
        return $this->budget;
    }

    public function setBudget(?float $budget): self
    {
        $this->budget = $budget === 0 ? null : $budget;

        return $this;
    }
    //endregion

    //region Concretizations
    /**
     * @var Collection|FundConcretization[]
     *
     * @Groups({"fund:read"})
     * @ORM\OneToMany(
     *     targetEntity="FundConcretization",
     *     mappedBy="fund",
     *     cascade={"persist", "remove"},
     *     orphanRemoval=true
     * )
     */
    private $concretizations;

    /**
     * @return Collection|FundConcretization[]
     */
    public function getConcretizations(): Collection
    {
        return $this->concretizations;
    }

    public function addConcretization(FundConcretization $concretization): self
    {
        if (!$this->concretizations->contains($concretization)) {
            $this->concretizations[] = $concretization;
            $concretization->setFund($this);
        }

        return $this;
    }

    public function removeConcretization(FundConcretization $concretization): self
    {
        if ($this->concretizations->contains($concretization)) {
            $this->concretizations->removeElement($concretization);
            // set the owning side to null (unless already changed)
            if ($concretization->getFund() === $this) {
                $concretization->setFund(null);
            }
        }

        return $this;
    }
    //endregion

    //region Criteria
    /**
     * @var array|null
     *
     * @Assert\All({
     *     @Assert\NotBlank(allowNull=false, normalizer="trim"),
     *     @Assert\Length(min=5, max=280, allowEmptyString=true,
     *         normalizer="trim"
     *     )
     * })
     * @Assert\NotBlank(allowNull=true)
     * @Groups({"elastica", "fund:read", "fund:write"})
     * @ORM\Column(type="json", nullable=true)
     */
    private ?array $criteria = null;

    public function getCriteria(): ?array
    {
        return $this->criteria;
    }

    public function setCriteria(?array $criteria): self
    {
        $this->criteria = is_array($criteria) && count($criteria)
            ? $criteria
            : null;

        return $this;
    }
    //endregion

    //region Description
    /**
     * @var string
     *
     * @Assert\NotBlank(allowNull=false,
     *     normalizer={NormalizerHelper::class, "stripHtml"}
     * )
     * @Assert\Length(min=20, max=10000, allowEmptyString=true,
     *     normalizer={NormalizerHelper::class, "stripHtml"}
     * )
     * @Groups({"elastica", "fund:read", "fund:write"})
     * @ORM\Column(type="text", length=30000, nullable=false)
     */
    private ?string $description = null;

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        if (NormalizerHelper::getTextLength($description) === 0) {
            $this->description = "";
        } else {
            $this->description = trim($description);
        }

        return $this;
    }
    //endregion

    //region FinalJuryDate
    /**
     * @var DateTimeImmutable|null
     *
     * @Groups({"fund:po-read", "fund:write", "fund:juror-read"})
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    private $finalJuryDate;

    public function getFinalJuryDate(): ?DateTimeImmutable
    {
        return $this->finalJuryDate;
    }

    public function setFinalJuryDate(?DateTimeImmutable $finalJuryDate): self
    {
        $this->finalJuryDate = $finalJuryDate;

        return $this;
    }
    //endregion

    //region Id
    /**
     * @var int
     *
     * @Groups({
     *     "fund:read",
     *     "fundApplication:read",
     *     "fundConcretization:read",
     *     "process:read",
     *     "project:read",
     * })
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    public function getId(): ?int
    {
        return $this->id;
    }
    //endregion

    //region Imprint
    /**
     * @var string|null
     *
     * @Assert\NotBlank(allowNull=true,
     *     normalizer={NormalizerHelper::class, "stripHtml"}
     * )
     * @Assert\Length(min=20, max=20000, allowEmptyString=true,
     *     normalizer={NormalizerHelper::class, "stripHtml"}
     * )
     * @Groups({"elastica", "fund:read", "fund:write"})
     * @ORM\Column(type="text", length=60000, nullable=true)
     */
    private ?string $imprint = null;

    public function getImprint(): ?string
    {
        return $this->imprint;
    }

    public function setImprint(?string $imprint): self
    {
        if (NormalizerHelper::getTextLength($imprint) === 0) {
            $this->imprint = null;
        } else {
            $this->imprint = trim($imprint);
        }

        return $this;
    }
    //endregion

    //region JurorsPerApplication
    /**
     * @var int
     *
     * @Assert\Type(type="integer")
     * @Assert\Range(min="1", max="100")
     * @Groups({"fund:po-read", "fund:write", "fund:juror-read"})
     * @ORM\Column(type="smallint", nullable=false, options={"default":2, "unsigned":true})
     */
    private $jurorsPerApplication = 2;

    public function getJurorsPerApplication(): int
    {
        return $this->jurorsPerApplication;
    }

    public function setJurorsPerApplication(int $jurorsPerApplication): self
    {
        $this->jurorsPerApplication = $jurorsPerApplication;

        return $this;
    }
    //endregion

    //region JuryCriteria
    /**
     * @var Collection|JuryCriterion[]
     *
     * @Groups({"fund:po-read", "fund:juror-read"})
     * @ORM\OneToMany(
     *     targetEntity="JuryCriterion",
     *     mappedBy="fund",
     *     cascade={"persist", "remove"},
     *     orphanRemoval=true
     * )
     */
    private $juryCriteria;

    /**
     * @return Collection|JuryCriterion[]
     */
    public function getJuryCriteria(): Collection
    {
        return $this->juryCriteria;
    }

    public function addJuryCriterion(JuryCriterion $criterion): self
    {
        if (!$this->juryCriteria->contains($criterion)) {
            $this->juryCriteria[] = $criterion;
            $criterion->setFund($this);
        }

        return $this;
    }

    public function removeJuryCriterion(JuryCriterion $criterion): self
    {
        if ($this->juryCriteria->contains($criterion)) {
            $this->juryCriteria->removeElement($criterion);
            // set the owning side to null (unless already changed)
            if ($criterion->getFund() === $this) {
                $criterion->setFund(null);
            }
        }

        return $this;
    }
    //endregion

    //region Logo
    /**
     * @var FundLogo
     *
     * @Groups({"fund:read", "fund:write"})
     * @ORM\ManyToOne(targetEntity="App\Entity\UploadedFileTypes\FundLogo")
     * @ORM\JoinColumn(nullable=true)
     */
    private $logo;

    public function getLogo(): ?FundLogo
    {
        return $this->logo;
    }

    public function setLogo(?FundLogo $logo): self
    {
        $this->logo = $logo;

        return $this;
    }
    //endregion

    //region MaximumGrant
    /**
     * @var float|null
     *
     * @Groups({"fund:read", "fund:write"})
     * @ORM\Column(type="float", precision=10, scale=0, nullable=true)
     */
    private $maximumGrant;

    public function getMaximumGrant(): ?float
    {
        return $this->maximumGrant;
    }

    public function setMaximumGrant(?float $maximumGrant): self
    {
        $this->maximumGrant = $maximumGrant === 0 ? null : $maximumGrant;

        return $this;
    }
    //endregion

    //region MinimumGrant
    /**
     * @var float|null
     *
     * @Groups({"fund:read", "fund:write"})
     * @ORM\Column(type="float", precision=10, scale=0, nullable=true)
     */
    private $minimumGrant;

    public function getMinimumGrant(): ?float
    {
        return $this->minimumGrant;
    }

    public function setMinimumGrant(?float $minimumGrant): self
    {
        $this->minimumGrant = $minimumGrant === 0 ? null : $minimumGrant;

        return $this;
    }
    //endregion

    //region Name
    /**
     * @var string
     *
     * @Assert\NotBlank(allowNull=false, normalizer="trim")
     * @Groups({"elastica", "fund:read", "fund:write", "process:read"})
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    private ?string $name = null;

    use NameFunctions;
    //endregion

    //region Process
    /**
     * @var Process
     *
     * @Assert\NotBlank(allowNull=false)
     * @Groups({"fund:read", "fund:create"})
     * @ORM\ManyToOne(targetEntity="Process", inversedBy="funds")
     * @ORM\JoinColumn(nullable=false)
     */
    private $process;

    public function getProcess(): ?Process
    {
        return $this->process;
    }

    public function setProcess(?Process $process): self
    {
        $this->process = $process;

        return $this;
    }
    //endregion

    //region RatingBegin
    /**
     * @var DateTimeImmutable|null
     *
     * @Groups({"fund:po-read", "fund:write", "fund:juror-read"})
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    private $ratingBegin;

    public function getRatingBegin(): ?DateTimeImmutable
    {
        return $this->ratingBegin;
    }

    public function setRatingBegin(?DateTimeImmutable $ratingBegin): self
    {
        $this->ratingBegin = $ratingBegin;

        return $this;
    }
    //endregion

    //region RatingEnd
    /**
     * @var DateTimeImmutable|null
     *
     * @Groups({"fund:po-read", "fund:write", "fund:juror-read"})
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    private $ratingEnd;

    public function getRatingEnd(): ?DateTimeImmutable
    {
        return $this->ratingEnd;
    }

    public function setRatingEnd(?DateTimeImmutable $ratingEnd): self
    {
        $this->ratingEnd = $ratingEnd;

        return $this;
    }
    //endregion

    //region Region
    /**
     * @var string
     *
     * @Assert\NotBlank(allowNull=false, normalizer="trim")
     * @Assert\Length(min=5, max=255, allowEmptyString=true, normalizer="trim")
     * @Groups({"elastica", "fund:read", "fund:write"})
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    private ?string $region = null;

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(string $region): self
    {
        $this->region = trim($region);

        return $this;
    }
    //endregion

    //region Sponsor
    /**
     * @var string
     *
     * @Assert\NotBlank(allowNull=false, normalizer="trim")
     * @Assert\Length(min=10, max=255, allowEmptyString=true,
     *     normalizer="trim"
     * )
     * @Groups({"elastica", "fund:read", "fund:write"})
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    private ?string $sponsor = null;

    public function getSponsor(): ?string
    {
        return $this->sponsor;
    }

    public function setSponsor(string $sponsor): self
    {
        $this->sponsor = trim($sponsor);

        return $this;
    }
    //endregion

    //region State
    /**
     * @var string
     *
     * @Assert\Choice(
     *     choices={
     *         Fund::STATE_ACTIVE,
     *         Fund::STATE_INACTIVE
     *     }
     * )
     * @Groups({"fund:read", "fund:update", "process:read"})
     * @ORM\Column(type="string", length=50, nullable=false, options={"default":"inactive"})
     */
    private $state = self::STATE_INACTIVE;

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(string $state): self
    {
        $this->state = $state;

        return $this;
    }
    //endregion

    //region SubmissionBegin
    /**
     * @var DateTimeImmutable|null
     *
     * @Groups({"fund:read", "fund:write"})
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    private $submissionBegin;

    public function getSubmissionBegin(): ?DateTimeImmutable
    {
        return $this->submissionBegin;
    }

    public function setSubmissionBegin(?DateTimeImmutable $submissionBegin): self
    {
        $this->submissionBegin = $submissionBegin;

        return $this;
    }
    //endregion

    //region SubmissionEnd
    /**
     * @var DateTimeImmutable|null
     *
     * @Groups({"fund:read", "fund:write"})
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    private $submissionEnd;

    public function getSubmissionEnd(): ?DateTimeImmutable
    {
        return $this->submissionEnd;
    }

    public function setSubmissionEnd(?DateTimeImmutable $submissionEnd): self
    {
        $this->submissionEnd = $submissionEnd;

        return $this;
    }
    //endregion

    public function __construct()
    {
        $this->applications = new ArrayCollection();
        $this->concretizations = new ArrayCollection();
        $this->juryCriteria = new ArrayCollection();
    }

    public function canBeActivated(): bool
    {
        if ($this->getState() !== self::STATE_INACTIVE) {
            return false;
        }

        if (!$this->getSubmissionBegin() || ! $this->getSubmissionEnd()) {
            return false;
        }

        if ($this->getSubmissionEnd() <= $this->getSubmissionBegin()) {
            return false;
        }

        if ($this->getSubmissionBegin() <= new DateTimeImmutable()) {
            return false;
        }

        if (count($this->concretizations) === 0) {
            return false;
        }

        // @todo Budget/Min/Max konsistent?
        // weitere Pflichtangaben?

        return true;
    }
}
