<?php
declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\AutoincrementId;
use App\Entity\Traits\CreatedAtFunctions;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Validation
 *
 * @ORM\Entity
 * @ORM\Table(uniqueConstraints={
 *     @ORM\UniqueConstraint(name="token_idx", columns={"user_id", "type", "token"})
 * })
 */
class Validation
{
    const TYPE_ACCOUNT        = 'account';
    const TYPE_RESET_PASSWORD = 'reset-password';
    const TYPE_CHANGE_EMAIL   = 'change-email';
    const TYPE_JURY_INVITE    = 'jury_invite';

    use AutoincrementId;

    //region Content
    /**
     * @var array|null
     *
     * @ORM\Column(type="small_json", length=512, nullable=true)
     */
    private $content;

    public function getContent(): ?array
    {
        return $this->content;
    }

    public function setContent(?array $content): self
    {
        $this->content = $content;

        return $this;
    }
    //endregion

    //region CreatedAt
    /**
     * @var DateTimeImmutable
     *
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime_immutable")
     */
    protected ?DateTimeImmutable $createdAt = null;

    use CreatedAtFunctions;
    //endregion

    //region ExpiresAt
    /**
     * @var DateTimeImmutable
     *
     * @ORM\Column(type="datetime_immutable", nullable=false)
     */
    private $expiresAt;

    public function isExpired(): bool
    {
        return $this->getExpiresAt() < new DateTimeImmutable();
    }

    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }
    //endregion

    //region Token
    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    private $token;

    public function getToken(): ?string
    {
        return $this->token;
    }

    /**
     * @throws \Exception when token generation fails
     */
    public function generateToken(): void
    {
        // we use BASE62 to shorten URLs instead of using 64 hex chars
        $base62 = new Tuupola\Base62;
        $this->setToken($base62->encode(random_bytes(32)));
    }

    public function setToken(string $token): self
    {
        $this->token = $token;

        return $this;
    }
    //endregion

    //region Type
    /**
     * @var string
     *
     * @Assert\Choice(
     *     choices={
     *         Validation::TYPE_ACCOUNT,
     *         Validation::TYPE_RESET_PASSWORD,
     *         Validation::TYPE_CHANGE_EMAIL,
     *         Validation::TYPE_JURY_INVITE
     *     }
     * )
     * @ORM\Column(type="string", length=50, nullable=false)
     */
    private $type;

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }
    //endregion

    //region User
    /**
     * @ORM\ManyToOne(targetEntity="User", inversedBy="validations")
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    public function getUser(): User
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
