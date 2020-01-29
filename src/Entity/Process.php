<?php
declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Entity\Traits\AutoincrementId;
use App\Entity\Traits\NameSlug;
use App\Entity\Traits\RequiredUniqueName;
use App\Entity\UploadedFileTypes\ProcessLogo;
use App\Validator\NormalizerHelper;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Process
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
 *         "groups"={"default:read", "process:read"},
 *         "enable_max_depth"=true,
 *         "swagger_definition_name"="Read"
 *     },
 *     denormalizationContext={
 *         "groups"={"default:write", "process:write"},
 *         "swagger_definition_name"="Write"
 *     }
 * )
 * @ORM\Entity
 * @ORM\EntityListeners({"App\Entity\Listener\ProcessListener"})
 * @UniqueEntity(fields={"name"}, message="Name already exists.")
 */
class Process
{
    use AutoincrementId;
    use RequiredUniqueName;
    use NameSlug;

    //region Criteria
    /**
     * @var array|null
     *
     * @Assert\All({
     *     @Assert\NotBlank(
     *         allowNull=false,
     *         message="validate.general.notBlank",
     *         normalizer="trim"
     *     ),
     *     @Assert\Length(min=5, max=100, allowEmptyString=true,
     *         minMessage="validate.general.tooShort",
     *         maxMessage="validate.general.tooLong"
     *     )
     * })
     * @Assert\NotBlank(allowNull=true, message="validate.general.notBlank")
     * @Groups({"elastica", "process:read", "process:write"})
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
     * @Assert\NotBlank(
     *     allowNull=false,
     *     message="validate.general.notBlank",
     *     normalizer={NormalizerHelper::class, "stripHtml"}
     * )
     * @Assert\Length(min=10, max=65535, allowEmptyString=true,
     *     minMessage="validate.general.tooShort",
     *     maxMessage="validate.general.tooLong",
     *     normalizer={NormalizerHelper::class, "stripHtml"}
     * )
     * @Groups({"elastica", "process:read", "process:write"})
     * @ORM\Column(type="text", length=65535, nullable=false)
     */
    private $description;

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        if (mb_strlen(trim(strip_tags($description))) === 0) {
            $this->description = "";
        } else {
            $this->description = trim($description);
        }

        return $this;
    }
    //endregion

    //region Funds
    /**
     * @Groups({"process:read"})
     * @ORM\OneToMany(targetEntity="Fund", mappedBy="process", orphanRemoval=true)
     */
    private $funds;

    /**
     * @return Collection|Fund[]
     */
    public function getFunds(): Collection
    {
        return $this->funds;
    }

    public function addFund(Fund $fund): self
    {
        if (!$this->funds->contains($fund)) {
            $this->funds[] = $fund;
            $fund->setProcess($this);
        }

        return $this;
    }

    public function removeFund(Fund $fund): self
    {
        if ($this->funds->contains($fund)) {
            $this->funds->removeElement($fund);
            // set the owning side to null (unless already changed)
            if ($fund->getProcess() === $this) {
                $fund->setProcess(null);
            }
        }

        return $this;
    }
    //endregion

    //region Goals
    /**
     * @var array
     *
     * @Assert\All({
     *     @Assert\NotBlank(
     *         allowNull=false,
     *         message="validate.general.notBlank",
     *         normalizer="trim"
     *     ),
     *     @Assert\Length(min=5, max=1000, allowEmptyString=true,
     *         minMessage="validate.general.tooShort",
     *         maxMessage="validate.general.tooLong",
     *         normalizer="trim"
     *     )
     * })
     * @Assert\NotBlank(allowNull=false, message="validate.general.notBlank")
     * @Groups({"elastica", "process:read", "process:write"})
     * @ORM\Column(type="json", nullable=false)
     */
    private ?array $goals = null;

    public function getGoals(): ?array
    {
        return $this->goals;
    }

    public function setGoals(array $goals): self
    {
        $this->goals = count($goals) ? $goals : null;

        return $this;
    }
    //endregion

    //region Imprint
    /**
     * @var string
     *
     * @Assert\NotBlank(
     *     allowNull=false,
     *     message="validate.general.notBlank",
     *     normalizer={NormalizerHelper::class, "stripHtml"}
     * )
     * @Assert\Length(min=5, max=65535, allowEmptyString=true,
     *     minMessage="validate.general.tooShort",
     *     maxMessage="validate.general.tooLong",
     *     normalizer={NormalizerHelper::class, "stripHtml"}
     * )
     * @Groups({"elastica", "process:read", "process:write"})
     * @ORM\Column(type="text", length=65535, nullable=false)
     */
    private $imprint;

    public function getImprint(): ?string
    {
        return $this->imprint;
    }

    public function setImprint(string $imprint): self
    {
        if (mb_strlen(trim(strip_tags($imprint))) === 0) {
            $this->imprint = "";
        } else {
            $this->imprint = trim($imprint);
        }

        return $this;
    }
    //endregion

    //region Logo
    /**
     * @var ProcessLogo
     *
     * @Groups({"elastica", "process:read", "process:write"})
     * @ORM\ManyToOne(targetEntity="App\Entity\UploadedFileTypes\ProcessLogo")
     * @ORM\JoinColumn(nullable=true)
     */
    private $logo;

    public function getLogo(): ?ProcessLogo
    {
        return $this->logo;
    }

    public function setLogo(?ProcessLogo $logo): self
    {
        $this->logo = $logo;

        return $this;
    }
    //endregion

    //region Projects
    /**
     * @Groups({"process:read"})
     * @ORM\OneToMany(targetEntity="Project", mappedBy="process", orphanRemoval=true)
     */
    private $projects;

    /**
     * @return Collection|Project[]
     */
    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function addProject(Project $project): self
    {
        if (!$this->projects->contains($project)) {
            $this->projects[] = $project;
            $project->setProcess($this);
        }

        return $this;
    }

    public function removeProject(Project $project): self
    {
        if ($this->projects->contains($project)) {
            $this->projects->removeElement($project);
            // set the owning side to null (unless already changed)
            if ($project->getProcess() === $this) {
                $project->setProcess(null);
            }
        }

        return $this;
    }
    //endregion

    //region Region
    /**
     * @var string
     *
     * @Assert\NotBlank(
     *     allowNull=false,
     *     message="validate.general.notBlank",
     *     normalizer="trim"
     * ),
     * @Groups({"elastica", "process:read", "process:write"})
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    private $region;

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

    public function __construct()
    {
        $this->funds = new ArrayCollection();
        $this->projects = new ArrayCollection();
    }
}
