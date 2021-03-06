<?php
declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Entity\UploadedFileTypes\ConcretizationImage;
use App\Validator\NormalizerHelper;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * FundConcretization
 *
 * No GET, the concretizations should be fetched via their fund, we would have
 * to filter for active funds/jury member for this fund.
 * (item GET is required by API Platform)
 *
 * @ApiResource(
 *     attributes={"security"="is_granted('IS_AUTHENTICATED_ANONYMOUSLY')"},
 *     collectionOperations={
 *         "post"={
 *             "security"="is_granted('ROLE_PROCESS_OWNER')",
 *             "validation_groups"={"Default", "fundConcretization:create"}
 *         }
 *     },
 *     itemOperations={
 *         "get"={
 *             "security"="is_granted('ROLE_ADMIN')",
 *         },
 *         "put"={
 *             "security"="is_granted('ROLE_PROCESS_OWNER')",
 *             "validation_groups"={"Default", "fundConcretization:write"}
 *         },
 *         "delete"={
 *             "security"="is_granted('DELETE', object)"
 *         }
 *     },
 *     input="App\Dto\ProjectInput",
 *     normalizationContext={
 *         "groups"={"default:read", "fundConcretization:read"},
 *         "swagger_definition_name"="Read"
 *     },
 *     denormalizationContext={
 *         "groups"={"default:write", "fundConcretization:write"},
 *         "swagger_definition_name"="Write"
 *     }
 * )
 *
 * @ORM\Entity
 * @ORM\Table(uniqueConstraints={
 *     @ORM\UniqueConstraint(name="unique_question", columns={"fund_id", "question"})
 * })
 * @UniqueEntity(fields={"question", "fund"}, message="Question already exists.")
 */
class FundConcretization
{
    //region Id
    /**
     * @var int
     *
     * @Groups({"fundConcretization:read", "fund:read"})
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    public function getId(): ?int
    {
        return $this->id;
    }
    //endregion

    //region Description
    /**
     * @var string|null
     *
     * @Assert\Length(min=10, max=65535, allowEmptyString=true,
     *     normalizer={NormalizerHelper::class, "stripHtml"}
     * )
     * @Groups({
     *     "fund:read",
     *     "fundConcretization:read",
     *     "fundConcretization:write",
     * })
     * @ORM\Column(type="text", length=65535, nullable=true)
     */
    private $description;

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        if (NormalizerHelper::getTextLength($description) === 0) {
            $this->description = null;
        } else {
            $this->description = trim($description);
        }

        return $this;
    }
    //endregion

    //region Fund
    /**
     * @var Fund
     *
     * @Groups({
     *     "fundConcretization:read",
     *     "fundConcretization:create",
     * })
     * @ORM\ManyToOne(targetEntity="Fund", inversedBy="concretizations", cascade={"persist"})
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

    //region Image
    /**
     * @var ConcretizationImage
     *
     * @Groups({
     *     "fund:read",
     *     "fundConcretization:read",
     *     "fundConcretization:write",
     * })
     * @ORM\ManyToOne(targetEntity="App\Entity\UploadedFileTypes\ConcretizationImage")
     * @ORM\JoinColumn(nullable=true)
     */
    private $image;

    public function getImage(): ?ConcretizationImage
    {
        return $this->image;
    }

    public function setImage(?ConcretizationImage $image): self
    {
        $this->image = $image;

        return $this;
    }
    //endregion

    //region MaxLength
    /**
     * @var int|null
     *
     * @Assert\Range(min = 1, max = 2000)
     * @Groups({
     *     "fund:read",
     *     "fundConcretization:read",
     *     "fundConcretization:write",
     * })
     * @ORM\Column(type="smallint", nullable=false, options={"unsigned": true})
     */
    private int $maxLength = 280;

    public function getMaxLength(): int
    {
        return $this->maxLength;
    }

    public function setMaxLength(int $maxLength): self
    {
        $this->maxLength = $maxLength;

        return $this;
    }
    //endregion

    //region Question
    /**
     * @var string
     *
     * @Assert\NotBlank(allowNull=false, normalizer="trim")
     * @Assert\Length(min=5, max=255, allowEmptyString=true, normalizer="trim")
     * @Groups({
     *     "fund:read",
     *     "fundConcretization:read",
     *     "fundConcretization:write",
     * })
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    private ?string $question = null;

    public function getQuestion(): ?string
    {
        return $this->question;
    }

    public function setQuestion(string $question): self
    {
        $this->question = trim($question);

        return $this;
    }
    //endregion
}
