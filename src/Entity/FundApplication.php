<?php
declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * FundApplication
 *
 * @todo: add action "submit"
 *
 * @ApiResource(
 *     attributes={"security"="is_granted('ROLE_USER')"},
 *     collectionOperations={
 *         "post"={
 *             "security"="is_granted('ROLE_USER')",
 *             "security_post_denormalize"="is_granted('CREATE', object)",
 *             "validation_groups"={"Default", "fundApplication:create"}
 *         }
 *     },
 *     itemOperations={
 *         "get"={
 *             "security"="is_granted('ROLE_ADMIN')"
 *         },
 *         "put"={
 *             "security"="is_granted('EDIT', object)",
 *             "validation_groups"={"Default", "fundApplication:write"}
 *         },
 *         "delete"={
 *             "security"="is_granted('DELETE', object)"
 *         }
 *     },
 *     normalizationContext={
 *         "groups"={"default:read", "fundApplication:read"},
 *         "enable_max_depth"=true,
 *         "swagger_definition_name"="Read"
 *     },
 *     denormalizationContext={
 *         "groups"={"default:write", "fundApplication:write"},
 *         "swagger_definition_name"="Write"
 *     }
 * )
 *
 * @ORM\Entity(
 *     repositoryClass="Gedmo\Sortable\Entity\Repository\SortableRepository"
 * )
 * @ORM\Table(indexes={
 *     @ORM\Index(name="order_idx", columns={"jury_order"}),
 *     @ORM\Index(name="state_idx", columns={"state"})
 * }, uniqueConstraints={
 *     @ORM\UniqueConstraint(name="projectapplication", columns={"fund_id", "project_id"})
 * })
 * @UniqueEntity(fields={"fund", "project"}, message="Duplicate fund application found.")
 */
class FundApplication
{
    const STATE_OPEN      = 'open';
    const STATE_SUBMITTED = 'submitted';

    const SELF_ASSESSMENT_0_PERCENT   = 0;
    const SELF_ASSESSMENT_25_PERCENT  = 25;
    const SELF_ASSESSMENT_50_PERCENT  = 50;
    const SELF_ASSESSMENT_75_PERCENT  = 75;
    const SELF_ASSESSMENT_100_PERCENT = 100;

    //region Concretizations
    /**
     * @var array|null
     *
     * @todo maxLength Validator
     * @Assert\All({
     *     @Assert\NotBlank(allowNull=false, normalizer="trim")
     * })
     * @Assert\NotBlank(allowNull=true)
     * @Groups({
     *     "project:read",
     *     "fundApplication:read",
     *     "fundApplication:write",
     * })
     * @ORM\Column(type="json", nullable=true)
     */
    private ?array $concretizations = null;

    public function getConcretizations(): ?array
    {
        return $this->concretizations;
    }

    public function setConcretizations(?array $concretizations): self
    {
        $this->concretizations = is_array($concretizations) && count($concretizations)
            ? $concretizations
            : null;

        return $this;
    }
    //endregion

    //region ConcretizationSelfAssessment
    /**
     * @var int
     *
     * @Groups({
     *     "project:read",
     *     "fundApplication:read",
     *     "fundApplication:write",
     * })
     * @Assert\Choice(
     *     choices={
     *         FundApplication::SELF_ASSESSMENT_0_PERCENT,
     *         FundApplication::SELF_ASSESSMENT_25_PERCENT,
     *         FundApplication::SELF_ASSESSMENT_50_PERCENT,
     *         FundApplication::SELF_ASSESSMENT_75_PERCENT,
     *         FundApplication::SELF_ASSESSMENT_100_PERCENT
     *     }
     * )
     * @ORM\Column(type="smallint", nullable=false, options={"unsigned":true})
     */
    private int $concretizationSelfAssessment = self::SELF_ASSESSMENT_0_PERCENT;

    public function getConcretizationSelfAssessment(): int
    {
        return $this->concretizationSelfAssessment;
    }

    public function setConcretizationSelfAssessment(int $selfAssessment): self
    {
        $this->concretizationSelfAssessment = $selfAssessment;

        return $this;
    }
    //endregion

    //region Fund
    /**
     * @var Fund
     *
     * @Groups({
     *     "fundApplication:read",
     *     "fundApplication:create",
     *     "project:read",
     * })
     * @MaxDepth(1)
     * @ORM\ManyToOne(targetEntity="Fund", inversedBy="applications")
     * @ORM\JoinColumn(nullable=false)
     * @Gedmo\SortableGroup
     */
    private $fund;

    public function getFund(): ?Fund
    {
        return $this->fund;
    }

    public function setFund(?Fund $fund): self
    {
        $this->fund = $fund;

        return $this;
    }
    //endregion

    //region Id
    /**
     * @var int
     *
     * @Groups({
     *     "project:read",
     *     "fundApplication:read",
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

    //region JuryComment
    /**
     * @var string|null
     *
     * @Groups({
     *     "project:po-read",
     *     "fundApplication:po-read",
     *     "fundApplication:po-write",
     * })
     * @ORM\Column(type="text", length=65535, nullable=true)
     */
    private $juryComment;

    public function getJuryComment(): ?string
    {
        return $this->juryComment;
    }

    public function setJuryComment(?string $juryComment): self
    {
        $this->juryComment = $juryComment;

        return $this;
    }
    //endregion

    //region JuryOrder
    /**
     * @var int|null
     *
     * @Groups({
     *     "project:po-read",
     *     "fundApplication:po-read",
     *     "fundApplication:po-write",
     * })
     * @ORM\Column(type="smallint", nullable=true)
     * @Gedmo\SortablePosition
     */
    private $juryOrder;

    public function getJuryOrder(): ?int
    {
        return $this->juryOrder;
    }

    public function setJuryOrder(?int $juryOrder): self
    {
        $this->juryOrder = $juryOrder;

        return $this;
    }
    //endregion

    //region Project
    /**
     * @var Project
     *
     * @Groups({
     *     "fund:read",
     *     "fundApplication:read",
     *     "fundApplication:create",
     * })
     * @MaxDepth(1)
     * @ORM\ManyToOne(targetEntity="Project", inversedBy="applications")
     * @ORM\JoinColumn(nullable=false)
     */
    private $project;

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;

        return $this;
    }
    //endregion

    //region Ratings
    /**
     * @var JuryRating[]|Collection
     *
     * @Groups({
     *     "project:po-read",
     *     "fundApplication:po-read",
     *     "fundApplication:po-write",
     *     "fundApplication:juror-read",
     *     "fundApplication:juror-write",
     * })
     * @ORM\OneToMany(targetEntity="JuryRating", mappedBy="application", orphanRemoval=true)
     */
    private $ratings;

    /**
     * @return Collection|JuryRating[]
     */
    public function getRatings(): Collection
    {
        return $this->ratings;
    }

    public function addRating(JuryRating $rating): self
    {
        if (!$this->ratings->contains($rating)) {
            $this->ratings[] = $rating;
            $rating->setApplication($this);
        }

        return $this;
    }

    public function removeRating(JuryRating $rating): self
    {
        if ($this->ratings->contains($rating)) {
            $this->ratings->removeElement($rating);
            // set the owning side to null (unless already changed)
            if ($rating->getApplication() === $this) {
                $rating->setApplication(null);
            }
        }

        return $this;
    }
    //endregion

    //region State
    /**
     * @var string
     *
     * @Groups({
     *     "project:read",
     *     "fundApplication:read",
     *     "fundApplication:write",
     * })
     * @ORM\Column(type="string", length=50, nullable=false)
     */
    private $state = self::STATE_OPEN;

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

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->ratings = new ArrayCollection();
    }
}
