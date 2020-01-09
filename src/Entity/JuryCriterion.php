<?php
declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Entity\Traits\AutoincrementId;
use App\Entity\Traits\RequiredName;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * JuryCriterion
 *
 * No GET, the juryCriteria should be fetched via their fund, we would have to
 * filter for active funds/jury member for this fund.
 * (item GET is required by API Platform)
 *
 * @ApiResource(
 *     attributes={"security"="is_granted('ROLE_USER')"},
 *     collectionOperations={
 *         "post"={
 *             "security"="is_granted('ROLE_PROCESS_OWNER')",
 *             "validation_groups"={"Default", "juryCriterion:create"}
 *         }
 *     },
 *     itemOperations={
 *         "get"={
 *             "security"="is_granted('ROLE_ADMIN')",
 *         },
 *         "put"={
 *             "security"="is_granted('ROLE_PROCESS_OWNER')",
 *             "validation_groups"={"Default", "juryCriterion:write"}
 *         },
 *         "delete"={
 *             "security"="is_granted('DELETE', object)"
 *         }
 *     },
 *     input="App\Dto\ProjectInput",
 *     normalizationContext={
 *         "groups"={"default:read", "juryCriterion:read"},
 *         "swagger_definition_name"="Read"
 *     },
 *     denormalizationContext={
 *         "allow_extra_attributes"=false,
 *         "groups"={"default:write", "juryCriterion:write"},
 *         "swagger_definition_name"="Write"
 *     }
 * )
 *
 * @ORM\Entity
 * @ORM\Table(uniqueConstraints={
 *     @ORM\UniqueConstraint(name="unique_name", columns={"fund_id", "name"})
 * })
 * @UniqueEntity(fields={"name", "fund"}, message="Name already exists.")
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
     * @ORM\ManyToOne(targetEntity="Fund", inversedBy="juryCriteria", cascade={"persist"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $fund;

    public function getFund(): ?Fund
    {
        return $this->fund;
    }

    public function setFund(?Fund $fund): self
    {
        $this->fund = $fund;

        return $this;
    }
    //endregion

    //region Question
    /**
     * @var string
     *
     * @Groups({
     *     "fund:read",
     *     "juryCriterion:read",
     *     "juryCriterion:write",
     * })
     * @Assert\NotBlank
     * @ORM\Column(type="string", length=5000, nullable=false)
     */
    private ?string $question = null;

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
