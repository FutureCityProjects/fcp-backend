<?php
declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\AutoincrementId;
use App\Entity\Traits\CreatedAtFunctions;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

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
     * @var DateTimeImmutable
     *
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime_immutable")
     */
    protected ?DateTimeImmutable $createdAt = null;
    /**
     * @var array|null
     *
     * @ORM\Column(type="small_json", length=512, nullable=true)
     */
    private $content;
    /**
     * @var DateTimeImmutable
     *
     * @ORM\Column(type="datetime_immutable", nullable=false)
     */
    private $expiresAt;
    //endregion

    //region CreatedAt
    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    private $token;

    use CreatedAtFunctions;
    //endregion

    //region ExpiresAt
    /**
     * @var string
     *
     * @ORM\Column(type="string", length=50, nullable=false)
     */
    private $type;
    /**
     * @ORM\ManyToOne(targetEntity="User", inversedBy="validations")
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

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

    //region Token

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

    public function getToken(): ?string
    {
        return $this->token;
    }
    //endregion

    //region Type

    public function setToken(string $token): self
    {
        $this->token = $token;

        return $this;
    }

    /**
     * @throws \Exception when token generation fails
     */
    public function generateToken(): void
    {
        // @todo use BASE62 to shorten URLs instead of using 64 hex chars
        // https://github.com/tuupola/base62
        $this->setToken(hash('sha256', random_bytes(32)));
    }

    public function getType(): ?string
    {
        return $this->type;
    }
    //endregion

    //region User

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

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
