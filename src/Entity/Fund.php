<?php
declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Entity\Traits\NameSlug;
use App\Entity\Traits\RequiredUniqueName;
use App\Entity\UploadedFileTypes\FundLogo;
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
 *         "put"={"security"="is_granted('ROLE_PROCESS_OWNER')"},
 *         "delete"={"security"="is_granted('ROLE_ADMIN')"}
 *     },
 *     normalizationContext={
 *         "groups"={"default:read", "fund:read"},
 *         "swagger_definition_name"="Read"
 *     },
 *     denormalizationContext={
 *         "allow_extra_attributes"=false,
 *         "groups"={"default:write", "fund:write"},
 *         "swagger_definition_name"="Write"
 *     }
 * )
 *
 * @ORM\Entity
 * @ORM\Table(indexes={
 *     @ORM\Index(name="state_idx", columns={"state"})
 * }, uniqueConstraints={
 *     @ORM\UniqueConstraint(name="name", columns={"name"})
 * })
 * @UniqueEntity(fields={"name"}, message="validate.fund.nameExists")
 */
class Fund
{
    const STATE_INACTIVE = 'inactive';
    const STATE_ACTIVE   = 'active';

    use RequiredUniqueName;
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
     * @var \DateTimeImmutable|null
     *
     * @Groups({"fund:po-read", "fund:write", "fund:juror-read"})
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    private $briefingDate;
    /**
     * @var float|null
     *
     * @Groups({"fund:read", "fund:write"})
     * @ORM\Column(type="float", precision=10, scale=0, nullable=true)
     */
    private $budget;
    /**
     * @var Collection|FundConcretization[]
     *
     * @Groups({"fund:read"})
     * @ORM\OneToMany(targetEntity="FundConcretization", mappedBy="fund", orphanRemoval=true)
     */
    private $concretizations;
    //endregion

    //region BriefingDate
    /**
     * @var array|null
     *
     * @Assert\All({
     *     @Assert\NotBlank,
     *     @Assert\Length(min=5, max=280, allowEmptyString=false,
     *         minMessage="This value is too short.",
     *         maxMessage="This value is too long."
     *     )
     * })
     * @Groups({"elastica", "fund:read", "fund:write"})
     * @ORM\Column(type="json", nullable=true)
     */
    private $criteria;
    /**
     * @var string
     *
     * @Groups({"elastica", "fund:read", "fund:write"})
     * @Assert\Length(min=20, max=65535, allowEmptyString=false,
     *     minMessage="This value is too short.",
     *     maxMessage="This value is too long."
     * )
     * @ORM\Column(type="text", length=65535, nullable=false)
     */
    private $description;
    /**
     * @var \DateTimeImmutable|null
     *
     * @Groups({"fund:po-read", "fund:write", "fund:juror-read"})
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    private $finalJuryDate;
    //endregion

    //region Budget
    /**
     * @var int
     *
     * @Groups({"fund:read"})
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;
    /**
     * @var string|null
     *
     * @Groups({"elastica", "fund:read", "fund:write"})
     * @Assert\NotBlank(allowNull=true)
     * @Assert\Length(min=20, max=65535, allowEmptyString=true,
     *     minMessage="This value is too short.",
     *     maxMessage="This value is too long."
     * )
     * @ORM\Column(type="text", length=65535, nullable=true)
     */
    private $imprint;
    /**
     * @var Collection|JuryCriterion[]
     *
     * @Groups({"fund:po-read", "fund:juror-read"})
     * @ORM\OneToMany(targetEntity="JuryCriterion", mappedBy="fund", orphanRemoval=true)
     */
    private $juryCriteria;
    //endregion

    //region Concretizations
    /**
     * @var int
     *
     * @Groups({"fund:po-read", "fund:write", "fund:juror-read"})
     * @ORM\Column(type="smallint", nullable=false, options={"default":2, "unsigned":true})
     */
    private $jurorsPerApplication = 2;
    /**
     * @var FundLogo
     *
     * @Groups({"fund:read", "fund:write"})
     * @ORM\ManyToOne(targetEntity="App\Entity\UploadedFileTypes\FundLogo")
     * @ORM\JoinColumn(nullable=true)
     */
    private $logo;
    /**
     * @var float|null
     *
     * @Groups({"fund:read", "fund:write"})
     * @ORM\Column(type="float", precision=10, scale=0, nullable=true)
     */
    private $maximumGrant;
    /**
     * @var float|null
     *
     * @Groups({"fund:read", "fund:write"})
     * @ORM\Column(type="float", precision=10, scale=0, nullable=true)
     */
    private $minimumGrant;
    //endregion

    //region Criteria
    /**
     * @var Process
     *
     * @Groups({"fund:read", "fund:create"})
     * @ORM\ManyToOne(targetEntity="Process", inversedBy="funds")
     * @ORM\JoinColumn(nullable=false)
     */
    private $process;
    /**
     * @var \DateTimeImmutable|null
     *
     * @Groups({"fund:po-read", "fund:write", "fund:juror-read"})
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    private $ratingBegin;
    /**
     * @var \DateTimeImmutable|null
     *
     * @Groups({"fund:po-read", "fund:write", "fund:juror-read"})
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    private $ratingEnd;
    //endregion

    //region Description
    /**
     * @var string
     *
     * @Groups({"elastica", "fund:read", "fund:write"})
     * @Assert\Length(min=5, max=255, allowEmptyString=false,
     *     minMessage="This value is too short.",
     *     maxMessage="This value is too long."
     * )
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    private $region;
    /**
     * @var string
     *
     * @Groups({"elastica", "fund:read", "fund:write"})
     * @Assert\Length(min=10, max=255, allowEmptyString=false,
     *     minMessage="This value is too short.",
     *     maxMessage="This value is too long."
     * )
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    private $sponsor;
    /**
     * @var string
     *
     * @Groups({"fund:read", "fund:update"})
     * @ORM\Column(type="string", length=50, nullable=false, options={"default":"inactive"})
     */
    private $state = self::STATE_INACTIVE;
    //endregion

    //region FinalJuryDate
    /**
     * @var \DateTimeImmutable|null
     *
     * @Groups({"fund:read", "fund:write"})
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    private $submissionBegin;
    /**
     * @var \DateTimeImmutable|null
     *
     * @Groups({"fund:read", "fund:write"})
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    private $submissionEnd;

    public function __construct()
    {
        $this->applications = new ArrayCollection();
        $this->concretizations = new ArrayCollection();
        $this->juryCriteria = new ArrayCollection();
    }
    //endregion

    //region Id

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
    //endregion

    //region Imprint

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

    public function getBriefingDate(): ?\DateTimeImmutable
    {
        return $this->briefingDate;
    }

    public function setBriefingDate(?\DateTimeImmutable $briefingDate): self
    {
        $this->briefingDate = $briefingDate;

        return $this;
    }
    //endregion

    //region JuryCriteria

    public function getBudget(): ?float
    {
        return $this->budget;
    }

    public function setBudget(?float $budget): self
    {
        $this->budget = $budget;

        return $this;
    }

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
    //endregion

    //region JurorsPerApplication

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

    public function getCriteria(): ?array
    {
        return $this->criteria;
    }

    public function setCriteria(?array $criteria): self
    {
        $this->criteria = $criteria;

        return $this;
    }
    //endregion

    //region Logo

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getFinalJuryDate(): ?\DateTimeImmutable
    {
        return $this->finalJuryDate;
    }
    //endregion

    //region MaximumGrant

    public function setFinalJuryDate(?\DateTimeImmutable $finalJuryDate): self
    {
        $this->finalJuryDate = $finalJuryDate;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getImprint(): ?string
    {
        return $this->imprint;
    }
    //endregion

    //region MinimumGrant

    public function setImprint(?string $imprint): self
    {
        $this->imprint = $imprint;

        return $this;
    }

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
    //endregion

    //region Process

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

    //region RatingBegin

    public function getLogo(): ?FundLogo
    {
        return $this->logo;
    }

    public function setLogo(?FundLogo $logo): self
    {
        $this->logo = $logo;

        return $this;
    }

    public function getMaximumGrant(): ?float
    {
        return $this->maximumGrant;
    }
    //endregion

    //region RatingEnd

    public function setMaximumGrant(?float $maximumGrant): self
    {
        $this->maximumGrant = $maximumGrant;

        return $this;
    }

    public function getMinimumGrant(): ?float
    {
        return $this->minimumGrant;
    }

    public function setMinimumGrant(?float $minimumGrant): self
    {
        $this->minimumGrant = $minimumGrant;

        return $this;
    }
    //endregion

    //region Region

    public function getProcess(): ?Process
    {
        return $this->process;
    }

    public function setProcess(?Process $process): self
    {
        $this->process = $process;

        return $this;
    }

    public function getRatingBegin(): ?\DateTimeImmutable
    {
        return $this->ratingBegin;
    }
    //endregion

    //region Sponsor

    public function setRatingBegin(?\DateTimeImmutable $ratingBegin): self
    {
        $this->ratingBegin = $ratingBegin;

        return $this;
    }

    public function getRatingEnd(): ?\DateTimeImmutable
    {
        return $this->ratingEnd;
    }

    public function setRatingEnd(?\DateTimeImmutable $ratingEnd): self
    {
        $this->ratingEnd = $ratingEnd;

        return $this;
    }
    //endregion

    //region State

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(string $region): self
    {
        $this->region = $region;

        return $this;
    }

    public function getSponsor(): ?string
    {
        return $this->sponsor;
    }
    //endregion

    //region SubmissionBegin

    public function setSponsor(string $sponsor): self
    {
        $this->sponsor = $sponsor;

        return $this;
    }

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

    //region SubmissionEnd

    public function getSubmissionBegin(): ?\DateTimeImmutable
    {
        return $this->submissionBegin;
    }

    public function setSubmissionBegin(?\DateTimeImmutable $submissionBegin): self
    {
        $this->submissionBegin = $submissionBegin;

        return $this;
    }

    public function getSubmissionEnd(): ?\DateTimeImmutable
    {
        return $this->submissionEnd;
    }
    //endregion

    public function setSubmissionEnd(?\DateTimeImmutable $submissionEnd): self
    {
        $this->submissionEnd = $submissionEnd;

        return $this;
    }
}
