<?php
declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * UserObjectRole
 *
 * @ORM\Entity
 * @ORM\Table(indexes={
 *     @ORM\Index(name="object_idx", columns={"object_id", "object_type"})
 * })
 */
class UserObjectRole
{
    const ROLE_JURY_MEMBER    = 'juryMember';
    const ROLE_PROCESS_OWNER  = 'processOwner';

    //region ObjectId
    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $objectId;

    public function getObjectId(): ?int
    {
        return $this->objectId;
    }

    public function setObjectId(int $id): self
    {
        $this->objectId = $id;

        return $this;
    }
    //endregion

    //region ObjectType
    /**
     * @var string
     *
     * @ORM\Column(type="string", length=50, nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $objectType;

    public function getObjectType(): ?string
    {
        return $this->objectType;
    }

    public function setObjectType(string $type): self
    {
        $this->objectType = $type;

        return $this;
    }
    //endregion

    //region Role
    /**
     * @var string
     * @Assert\Choice(
     *     choices={
     *         UserObjectRole::ROLE_JURY_MEMBER,
     *         UserObjectRole::ROLE_PROCESS_OWNER
     *     }
     * )
     * @ORM\Column(type="string", length=50, nullable=false)
     */
    private $role;

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

    //region User
    /**
     * @var User
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\ManyToOne(targetEntity="User", inversedBy="objectRoles")
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    public function getUser(): User
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
