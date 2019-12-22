<?php
declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Validator\Constraints as AppAssert;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
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
 *     attributes={"security"="is_granted('ROLE_USER')"},
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
 *         "swagger_definition_name"="Read"
 *     },
 *     denormalizationContext={
 *         "allow_extra_attributes"=false,
 *         "groups"={"default:write", "projectMembership:write"},
 *         "swagger_definition_name"="Write"
 *     }
 * )
 *
 * @AppAssert\ValidMembershipRequest(groups={"projectMembership:create"})
 * @AppAssert\ValidMembershipUpdate(groups={"projectMembership:write"})
 * @ORM\Entity
 * @UniqueEntity(fields={"project", "user"}, message="Duplicate membership found.")
 */
class ProjectMembership
{
    const ROLE_APPLICANT = 'applicant';
    const ROLE_MEMBER = 'member';
    const ROLE_OWNER  = 'owner';

    //region Motivation
    /**
     * @var string
     * @Groups({
     *     "project:read",
     *     "projectMembership:read",
     *     "projectMembership:write",
     *     "user:read"
     * })
     * @Assert\Length(min=20, max=1000, allowEmptyString=false,
     *     minMessage="This value is too short.",
     *     maxMessage="This value is too long.")
     * @ORM\Column(type="text", length=1000, nullable=false)
     */
    private ?string $motivation = null;
    /**
     * @var Project
     * @Groups({
     *     "projectMembership:read",
     *     "projectMembership:create",
     *     "user:read"
     * })
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="Project", inversedBy="memberships")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Project $project = null;
    /**
     * @var string
     * @Groups({
     *     "project:read",
     *     "projectMembership:read",
     *     "projectMembership:write",
     *     "user:read"
     * })
     * @ORM\Column(type="string", length=50, nullable=false)
     */
    private ?string $role = null;
    //endregion

    //region Project
    /**
     * @var string
     * @Groups({
     *     "project:read",
     *     "projectMembership:read",
     *     "projectMembership:write",
     *     "user:read"
     * })
     * @Assert\Length(min=20, max=1000, allowEmptyString=false,
     *     minMessage="This value is too short.",
     *     maxMessage="This value is too long.")
     * @ORM\Column(type="text", length=1000, nullable=false)
     */
    private ?string $skills = null;
    /**
     * @var string
     * @Groups({
     *     "project:read",
     *     "projectMembership:read",
     *     "projectMembership:write",
     *     "user:read"
     * })
     * @ORM\Column(type="text", length=1000, nullable=true)
     */
    private ?string $tasks = null;
    /**
     * @var User
     *
     * @Groups({
     *     "project:read",
     *     "projectMembership:create",
     *     "projectMembership:read"
     * })
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\ManyToOne(targetEntity="User", inversedBy="projectMemberships")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?User $user = null;
    //endregion

    //region Role

    public function getMotivation(): ?string
    {
        return $this->motivation;
    }

    public function setMotivation(?string $motivation): self
    {
        $this->motivation = $motivation;

        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }
    //endregion

    //region Skills

    public function setProject(Project $project): self
    {
        $this->project = $project;

        return $this;
    }

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

    //region Tasks

    public function getSkills(): ?string
    {
        return $this->skills;
    }

    public function setSkills(?string $skills): self
    {
        $this->skills = $skills;

        return $this;
    }

    public function getTasks(): ?string
    {
        return $this->tasks;
    }
    //endregion

    //region User

    public function setTasks(?string $tasks): self
    {
        $this->tasks = $tasks;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }
    //endregion
}
