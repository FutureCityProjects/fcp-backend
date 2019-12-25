<?php
declare(strict_types=1);

namespace App\Entity;


use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use App\Entity\Traits\AutoincrementId;
use App\Entity\Traits\CreatedAtFunctions;
use App\Validator\Constraints as AppAssert;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * User
 *
 * Only Admins and ProcessOwners can read users,
 * only Admins can create/update/delete users.
 *
 * @todo
 * * new endpoint for user profile (dont use item:PUT, we maybe want to allow
 *   setting of other properties, trigger events etc)
 * * new endpoint for changing email (create validation)
 * * new endpoint for PW reset (create validation)
 *
 * @ApiResource(
 *     attributes={"security"="is_granted('ROLE_ADMIN') or is_granted('ROLE_PROCESS_OWNER')"},
 *     collectionOperations={
 *         "get",
 *         "post"={
 *             "security"="is_granted('ROLE_ADMIN')",
 *             "validation_groups"={"Default", "user:create"}
 *         }
 *     },
 *     itemOperations={
 *         "get",
 *         "put"={
 *             "security"="is_granted('ROLE_ADMIN')",
 *             "validation_groups"={"Default", "user:update"}
 *         },
 *         "delete"={
 *             "security"="is_granted('ROLE_ADMIN')"
 *         }
 *     },
 *     input="App\Dto\UserInput",
 *     normalizationContext={
 *         "groups"={"default:read", "user:read"},
 *         "swagger_definition_name"="Read"
 *     },
 *     denormalizationContext={
 *         "allow_extra_attributes"=false,
 *         "groups"={"default:write", "user:write"},
 *         "swagger_definition_name"="Write"
 *     }
 * )
 * @ApiFilter(SearchFilter::class, properties={"username": "exact"})
 * @ApiFilter(BooleanFilter::class, properties={"isActive"})
 * @ApiFilter(BooleanFilter::class, properties={"isValidated"})
 * @ApiFilter(ExistsFilter::class, properties={"deletedAt"})
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
 * @ORM\Table(indexes={
 *     @ORM\Index(name="deleted_idx", columns={"deleted_at"})
 * }, uniqueConstraints={
 *     @ORM\UniqueConstraint(name="email", columns={"email"})
 * })
 * @UniqueEntity(fields={"email"}, message="Email already exists.")
 * @UniqueEntity(fields={"username"}, message="Username already exists.")
 */
class User implements UserInterface
{
    public const ROLE_ADMIN         = 'ROLE_ADMIN';
    public const ROLE_PROCESS_OWNER = 'ROLE_PROCESS_OWNER';
    public const ROLE_USER          = 'ROLE_USER';

    use AutoincrementId;

    //region Username
    /**
     * User names must start with a letter may contain only letters, digits,
     * dots, hyphens and underscores, thy must contain at least two letters
     * (first regex).
     * User names may not be in the format "deleted_{0-9}" as this is reserved
     * for deleted users (second regex).
     *
     * @Assert\NotBlank
     * @Assert\Regex(
     *     pattern="/^[a-zA-Z]+[a-zA-Z0-9._-]*[a-zA-Z][a-zA-Z0-9._-]*$/",
     *     message="Username is not valid."
     * )
     * @Assert\Regex(
     *     pattern="/^deleted_[0-9]+$/",
     *     match=false,
     *     message="Username is not valid."
     * )
     * @Groups({"user:read", "user:create", "user:admin-write", "project:read"})
     * @ORM\Column(type="string", length=255, nullable=false, unique=true)
     */
    private ?string $username = null;

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }
    //endregion

    //region Password
    /**
     * @var string
     *
     * @Assert\NotBlank
     * @Groups({"user:write"})
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    private $password;

    /**
     * @see UserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }
    //endregion

    //region Email
    /**
     * @var string
     *
     * @Assert\Email
     * @Assert\NotBlank
     * @Assert\Regex(
     *     pattern="/^deleted_[0-9]+@fcp.user$/",
     *     match=false,
     *     message="Email is not valid."
     * )
     * @Groups({"user:read", "user:write"})
     * @ORM\Column(type="string", length=255, nullable=false, unique=true)
     */
    private $email;

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }
    //endregion

    //region Roles
    /**
     * @var array
     *
     * @Groups({"user:read", "user:write"})
     * @Assert\All({
     *     @Assert\NotBlank,
     *     @Assert\Choice(
     *         choices={
     *             User::ROLE_ADMIN,
     *             User::ROLE_PROCESS_OWNER,
     *             User::ROLE_USER
     *         },
     *     )
     * })
     *
     * @ORM\Column(type="small_json", length=255, nullable=true)
     */
    private $roles = [];

    /**
     * Returns true if the user has the given role, else false.
     *
     * @param string $role
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles());
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;

        // guarantee every user at least has ROLE_USER
        $roles[] = self::ROLE_USER;

        return array_unique($roles);
    }

    public function setRoles(array $roles = []): self
    {
        // make sure every role is stored only once, remove ROLE_USER
        $this->roles = array_diff(array_unique($roles), [self::ROLE_USER]);

        return $this;
    }
    //endregion

    //region FirstName
    /**
     * @var string
     * @AppAssert\ValidPersonName
     * @Groups({"user:read", "user:write", "project:read"})
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $firstName = null;

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }
    //endregion

    //region LastName
    /**
     * @var string
     * @AppAssert\ValidPersonName
     * @Groups({"user:read", "user:write", "project:read"})
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $lastName = null;

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): self
    {
        $this->lastName = $lastName;

        return $this;
    }
    //endregion

    //region IsActive
    /**
     * @Groups({"user:read", "user:write"})
     * @ORM\Column(type="boolean", options={"default":true})
     */
    private $isActive = true;

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function isActive(): bool
    {
        return $this->getIsActive();
    }

    public function setIsActive(bool $isActive = true): self
    {
        $this->isActive = $isActive;

        return $this;
    }
    //endregion

    //region IsValidated
    /**
     * @Groups({"user:read", "user:write"})
     * @ORM\Column(type="boolean", options={"default":false})
     */
    private $isValidated = false;

    public function isValidated(): bool
    {
        return $this->getIsValidated();
    }

    public function getIsValidated(): bool
    {
        return $this->isValidated;
    }

    public function setIsValidated(bool $isValidated = true): self
    {
        $this->isValidated = $isValidated;

        return $this;
    }
    //endregion

    //region CreatedAt
    /**
     * @var DateTimeImmutable
     *
     * @Assert\NotBlank(allowNull=true)
     * @Groups({"user:read"})
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime_immutable")
     */
    protected ?DateTimeImmutable $createdAt = null;

    use CreatedAtFunctions;
    //endregion

    //region DeletedAt
    /**
     * @var DateTimeImmutable
     * @Groups({"user:admin-read"})
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    protected ?DateTimeImmutable $deletedAt = null;

    /**
     * Sets deletedAt.
     *
     * @param DateTimeImmutable|null $deletedAt
     *
     * @return $this
     */
    public function setDeletedAt(?DateTimeImmutable $deletedAt): self
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    /**
     * Returns deletedAt.
     *
     * @return DateTimeImmutable
     */
    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    /**
     * Is deleted?
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return null !== $this->deletedAt;
    }

    /**
     * Sets the deletedAt timestamp to mark the object as deleted.
     * Removes private and/or identifying data to comply with privacy laws.
     *
     * @return $this
     */
    public function markDeleted(): self
    {
        $this->deletedAt = new DateTimeImmutable();

        // remove private / identifying data
        $this->setUsername('deleted_'.$this->getId());
        $this->setEmail('deleted_'.$this->getId().'@fcp.user');
        $this->setPassword('');
        $this->setFirstName(null);
        $this->setLastName(null);

        // remove privileges
        foreach ($this->getObjectRoles() as $objectRole) {
            $this->removeObjectRole($objectRole);
        }
        foreach ($this->getProjectMemberships() as $membership) {
            $this->removeProjectMembership($membership);
        }

        return $this;
    }
    //endregion

    //region ObjectRoles
    /**
     * @Groups({"user:read"})
     * @ORM\OneToMany(
     *     targetEntity="UserObjectRole",
     *     mappedBy="user",
     *     cascade={"persist", "remove"},
     *     orphanRemoval=true
     * )
     */
    private $objectRoles;

    /**
     * @return Collection|UserObjectRole[]
     */
    public function getObjectRoles(): Collection
    {
        return $this->objectRoles;
    }

    public function addObjectRole(UserObjectRole $objectRole): self
    {
        if (!$this->objectRoles->contains($objectRole)) {
            $this->objectRoles[] = $objectRole;
            $objectRole->setUser($this);
        }

        return $this;
    }

    public function removeObjectRole(UserObjectRole $objectRole): self
    {
        if ($this->objectRoles->contains($objectRole)) {
            $this->objectRoles->removeElement($objectRole);
            // set the owning side to null (unless already changed)
            if ($objectRole->getUser() === $this) {
                $objectRole->setUser(null);
            }
        }

        return $this;
    }
    //endregion

    //region ProjectMemberships
    /**
     * @var Collection|ProjectMembership[]
     * @Groups({"user:read"})
     * @ORM\OneToMany(
     *     targetEntity="ProjectMembership",
     *     mappedBy="user",
     *     cascade={"persist", "remove"},
     *     orphanRemoval=true
     * )
     */
    private $projectMemberships;

    /**
     * @return Collection|ProjectMembership[]
     */
    public function getProjectMemberships(): Collection
    {
        return $this->projectMemberships;
    }

    public function addProjectMembership(ProjectMembership $member): self
    {
        if (!$this->projectMemberships->contains($member)) {
            $this->projectMemberships[] = $member;
            $member->setUser($this);
        }

        return $this;
    }

    public function removeProjectMembership(ProjectMembership $member): self
    {
        if ($this->projectMemberships->contains($member)) {
            $this->projectMemberships->removeElement($member);
            // set the owning side to null (unless already changed)
            if ($member->getUser() === $this) {
                $member->setUser(null);
            }
        }

        return $this;
    }
    //endregion

    //region CreatedProjects
    /**
     * @ORM\OneToMany(targetEntity="Project", mappedBy="user", mappedBy="createdBy")
     */
    private $createdProjects;

    /**
     * @return Collection|Validation[]
     */
    public function getCreatedProjects(): Collection
    {
        return $this->createdProjects;
    }

    public function addCreatedProject(Project $project): self
    {
        if (!$this->createdProjects->contains($project)) {
            $this->createdProjects[] = $project;
            $project->setCreatedBy($this);
        }

        return $this;
    }

    public function removeCreatedProject(Project $project): self
    {
        if ($this->createdProjects->contains($project)) {
            $this->createdProjects->removeElement($project);
            // set the owning side to null (unless already changed)
            if ($project->getCreatedBy() === $this) {
                $project->setCreatedBy(null);
            }
        }

        return $this;
    }
    //endregion

    //region Validations
    /**
     * @ORM\OneToMany(targetEntity="Validation", mappedBy="user", orphanRemoval=true)
     */
    private $validations;

    /**
     * @return Collection|Validation[]
     */
    public function getValidations(): Collection
    {
        return $this->validations;
    }

    public function addValidation(Validation $validation): self
    {
        if (!$this->validations->contains($validation)) {
            $this->validations[] = $validation;
            $validation->setUser($this);
        }

        return $this;
    }

    public function removeValidation(Validation $validation): self
    {
        if ($this->validations->contains($validation)) {
            $this->validations->removeElement($validation);
            // set the owning side to null (unless already changed)
            if ($validation->getUser() === $this) {
                $validation->setUser(null);
            }
        }

        return $this;
    }
    //endregion

    public function __construct()
    {
        $this->createdProjects = new ArrayCollection();
        $this->objectRoles = new ArrayCollection();
        $this->projectMemberships = new ArrayCollection();
        $this->validations = new ArrayCollection();
    }

    /**
     * @see UserInterface
     */
    public function getSalt()
    {
        // relict in UserInterface from times when the salt was stored
        // separately from the password...
        return null;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }
}
