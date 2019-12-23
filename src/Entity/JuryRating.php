<?php
declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * JuryRating
 *
 * @ORM\Entity
 */
class JuryRating
{
    const STATE_OPEN = 'open';

    //region Application
    /**
     * @var FundApplication
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\ManyToOne(targetEntity="FundApplication", inversedBy="ratings")
     * @ORM\JoinColumn(nullable=false)
     */
    private $application;

    public function getApplication(): FundApplication
    {
        return $this->application;
    }

    public function setApplication(FundApplication $application): self
    {
        $this->application = $application;

        return $this;
    }
    //endregion

    //region Juror
    /**
     * @var User
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(nullable=false)
     */
    private $juror;

    public function getJuror(): User
    {
        return $this->juror;
    }

    public function setJuror(User $user): self
    {
        $this->juror = $user;

        return $this;
    }
    //endregion

    //region Ratings
    /**
     * @var array|null
     *
     * @ORM\Column(type="json", nullable=true)
     */
    private $ratings;

    public function getRatings(): ?array
    {
        return $this->ratings;
    }

    public function setRatings(?array $ratings): self
    {
        $this->ratings = $ratings;

        return $this;
    }
    //endregion

    //region State
    /**
     * @var string
     *
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
}
