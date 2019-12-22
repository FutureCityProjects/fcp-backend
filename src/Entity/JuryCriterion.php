<?php
declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\AutoincrementId;
use App\Entity\Traits\RequiredName;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * JuryCriteria
 *
 * @ORM\Entity
 */
class JuryCriterion
{
    use AutoincrementId;
    use RequiredName;

    //region Fund
    /**
     * @var Fund
     *
     * @Groups({
     *     "juryCriterion:read",
     *     "juryCriterion:create",
     * })
     * @ORM\ManyToOne(targetEntity="Fund", inversedBy="juryCriteria")
     * @ORM\JoinColumn(nullable=false)
     */
    private $fund;
    /**
     * @var string
     *
     * @Groups({
     *     "fund:read",
     *     "juryCriterion:read",
     *     "juryCriterion:write",
     * })
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    private $question;

    public function getFund(): ?Fund
    {
        return $this->fund;
    }
    //endregion

    //region Question

    public function setFund(?Fund $fund): self
    {
        $this->fund = $fund;

        return $this;
    }

    public function getQuestion(): ?string
    {
        return $this->question;
    }

    public function setQuestion(string $question): self
    {
        $this->question = $question;

        return $this;
    }
    //endregion
}
