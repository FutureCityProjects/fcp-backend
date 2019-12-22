<?php
declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Entity\Traits\AutoincrementId;
use App\Entity\Traits\NameSlug;
use App\Entity\Traits\RequiredUniqueName;
use App\Entity\UploadedFileTypes\ProcessLogo;
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
 *         "swagger_definition_name"="Read"
 *     },
 *     denormalizationContext={
 *         "allow_extra_attributes"=false,
 *         "groups"={"default:write", "process:write"},
 *         "swagger_definition_name"="Read"
 *     }
 * )
 * @ORM\Entity
 * @UniqueEntity(fields={"name"}, message="validate.process.nameExists")
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
     *     @Assert\NotBlank,
     *     @Assert\Length(min=5, max=1000, allowEmptyString=false,
     *         minMessage="This value is too short.",
     *         maxMessage="This value is too long."
     *     )
     * })
     * @Assert\NotBlank(allowNull=true)
     * @Groups({"elastica", "process:read", "process:write"})
     * @ORM\Column(type="json", nullable=true)
     */
    private $criteria;
    /**
     * @var string
     *
     * @Assert\NotBlank
     * @Groups({"elastica", "process:read", "process:write"})
     * @ORM\Column(type="text", length=65535, nullable=false)
     */
    private $description;
    /**
     * @Groups({"process:read"})
     * @ORM\OneToMany(targetEntity="Fund", mappedBy="process", orphanRemoval=true)
     */
    private $funds;
    //endregion

    //region Description
    /**
     * @var string
     *
     * @Assert\NotBlank
     * @Groups({"elastica", "process:read", "process:write"})
     * @ORM\Column(type="text", length=65535, nullable=false)
     */
    private $imprint;
    /**
     * @var ProcessLogo
     *
     * @Groups({"elastica", "process:read", "process:write"})
     * @ORM\ManyToOne(targetEntity="App\Entity\UploadedFileTypes\ProcessLogo")
     * @ORM\JoinColumn(nullable=true)
     */
    private $logo;
    /**
     * @Groups({"process:read"})
     * @ORM\OneToMany(targetEntity="Project", mappedBy="process", orphanRemoval=true)
     */
    private $projects;
    //endregion

    //region Funds
    /**
     * @var string
     *
     * @Assert\NotBlank
     * @Groups({"elastica", "process:read", "process:write"})
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    private $region;
    /**
     * @var array
     *
     * @Assert\All({
     *     @Assert\NotBlank,
     *     @Assert\Length(min=5, max=1000, allowEmptyString=false,
     *         minMessage="This value is too short.",
     *         maxMessage="This value is too long."
     *     )
     * })
     * @Assert\NotBlank
     * @Groups({"elastica", "process:read", "process:write"})
     * @ORM\Column(type="json", nullable=false)
     */
    private $targets;

    public function __construct()
    {
        $this->funds = new ArrayCollection();
        $this->projects = new ArrayCollection();
    }

    public function getCriteria(): ?array
    {
        return $this->criteria;
    }
    //endregion

    //region Imprint

    public function setCriteria(?array $criteria): self
    {
        $this->criteria = $criteria;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }
    //endregion

    //region Logo

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

    //region Projects

    public function getImprint(): ?string
    {
        return $this->imprint;
    }

    public function setImprint(string $imprint): self
    {
        $this->imprint = $imprint;

        return $this;
    }

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

    //region Region

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

    //region Targets

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(string $region): self
    {
        $this->region = $region;

        return $this;
    }

    public function getTargets(): ?array
    {
        return $this->targets;
    }
    //endregion

    public function setTargets(array $targets): self
    {
        $this->targets = $targets;

        return $this;
    }
}
