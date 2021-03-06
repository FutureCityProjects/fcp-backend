<?php
declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Validator\Constraints as AppAssert;
use App\Validator\NormalizerHelper;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * ProjectMembership
 *
 * Collection cannot be queried, memberships can only be retrieved via the
 * user or project relations.
 * Item GET is required for API Platform to work, thus restricted to admins,
 * should not be used.
 *
 * @ApiResource(
 *     attributes={
 *         "security"="is_granted('ROLE_USER')",
 *         "force_eager"=false,
 *     },
 *     collectionOperations={
 *         "post"={
 *             "security"="is_granted('ROLE_USER')",
 *             "validation_groups"={"Default", "projectMembership:create"}
 *         }
 *     },
 *     itemOperations={
 *         "get"={
 *             "security"="is_granted('ROLE_ADMIN')"
 *         },
 *         "put"={
 *             "security"="is_granted('EDIT', object)",
 *             "validation_groups"={"Default", "projectMembership:write"}
 *         },
 *         "delete"={
 *             "security"="is_granted('DELETE', object)"
 *         }
 *     },
 *     input="App\Dto\ProjectInput",
 *     normalizationContext={
 *         "groups"={"default:read", "projectMembership:read"},
 *         "enable_max_depth"=true,
 *         "swagger_definition_name"="Read"
 *     },
 *     denormalizationContext={
 *         "groups"={"default:write", "projectMembership:write"},
 *         "swagger_definition_name"="Write"
 *     }
 * )
 *
 * @AppAssert\ValidMembershipRequest(groups={"projectMembership:create"})
 * @AppAssert\ValidMembershipUpdate(groups={"projectMembership:write"})
 *
 * @todo auch die einzelnen Validatoren im ProjectMembershipValidator zusammenführen?
 * @Assert\Callback(
 *     groups={"user:register"},
 *     callback={"App\Validator\ProjectMembershipValidator", "validateRegistration"}
 * )
 * @ORM\Entity
 * @UniqueEntity(fields={"project", "user"}, message="validate.projectMembership.duplicateMembership")
 */
class ProjectMembership
{
    public const ROLE_APPLICANT = 'applicant';
    public const ROLE_MEMBER    = 'member';
    public const ROLE_OWNER     = 'owner';

    //region Motivation
    /**
     * @var string
     *
     * @Assert\NotBlank(allowNull=false, normalizer="trim")
     * @Assert\Length(min=10, max=1000, allowEmptyString=true,
     *     normalizer="trim")
     * @Groups({
     *     "project:read",
     *     "projectMembership:read",
     *     "projectMembership:write",
     *     "user:read",
     *     "user:register",
     * })
     * @ORM\Column(type="text", length=1000, nullable=false)
     */
    private ?string $motivation = null;

    public function getMotivation(): ?string
    {
        return $this->motivation;
    }

    public function setMotivation(?string $motivation): self
    {
        if (NormalizerHelper::getTextLength($motivation) === 0) {
            $this->motivation = null;
        } else {
            $this->motivation = trim($motivation);
        }

        return $this;
    }
    //endregion

    //region Project
    /**
     * @var Project
     * @Groups({
     *     "projectMembership:read",
     *     "projectMembership:create",
     *     "user:read",
     *     "user:register",
     * })
     * @MaxDepth(1)
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="Project", inversedBy="memberships")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private ?Project $project = null;

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(Project $project): self
    {
        $this->project = $project;

        return $this;
    }
    //endregion

    //region Role
    /**
     * @var string
     * @Assert\Choice(
     *     choices={
     *         ProjectMembership::ROLE_APPLICANT,
     *         ProjectMembership::ROLE_MEMBER,
     *         ProjectMembership::ROLE_OWNER
     *     }
     * )
     * @Groups({
     *     "project:read",
     *     "projectMembership:read",
     *     "projectMembership:write",
     *     "user:read",
     *     "user:register",
     * })
     * @ORM\Column(type="string", length=50, nullable=false)
     */
    private ?string $role = null;

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;

        return $this;
    }
    //endregion

    //region Skills
    /**
     * @var string
     *
     * @Assert\NotBlank(allowNull=false, normalizer="trim")
     * @Assert\Length(min=10, max=1000, allowEmptyString=true, normalizer="trim")
     * @Groups({
     *     "project:read",
     *     "projectMembership:read",
     *     "projectMembership:write",
     *     "user:read",
     *     "user:register",
     * })
     * @ORM\Column(type="text", length=1000, nullable=false)
     */
    private ?string $skills = null;

    public function getSkills(): ?string
    {
        return $this->skills;
    }

    public function setSkills(?string $skills): self
    {
        if (NormalizerHelper::getTextLength($skills) === 0) {
            $this->skills = null;
        } else {
            $this->skills = trim($skills);
        }

        return $this;
    }
    //endregion

    //region Tasks
    /**
     * @var string
     *
     * @Assert\NotBlank(allowNull=true)
     * @Groups({
     *     "project:read",
     *     "projectMembership:read",
     *     "projectMembership:write",
     *     "user:read",
     *     "user:register",
     * })
     * @ORM\Column(type="text", length=1000, nullable=true)
     */
    private ?string $tasks = null;

    public function getTasks(): ?string
    {
        return $this->tasks;
    }

    public function setTasks(?string $tasks): self
    {
        if (NormalizerHelper::getTextLength($tasks) === 0) {
            $this->tasks = null;
        } else {
            $this->tasks = trim($tasks);
        }

        return $this;
    }
    //endregion

    //region User
    /**
     * @var User
     *
     * @Assert\NotBlank(allowNull=true, groups={"user:register"})
     * @Assert\NotBlank(
     *     allowNull=false,
     *     groups={"projectMembership:create"}
     * )
     * @Groups({
     *     "project:read",
     *     "projectMembership:create",
     *     "projectMembership:read",
     * })
     * @MaxDepth(2)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\ManyToOne(targetEntity="User", inversedBy="projectMemberships")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private ?User $user = null;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }
    //endregion
}
