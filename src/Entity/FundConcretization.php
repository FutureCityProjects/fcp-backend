<?php
declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\AutoincrementId;
use App\Entity\UploadedFileTypes\ConcretizationImage;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * FundConcretization
 *
 * @ORM\Entity
 */
class FundConcretization
{
    use AutoincrementId;

    //region Description
    /**
     * @var string|null
     *
     * @Groups({
     *     "fund:read",
     *     "fundConcretization:read",
     *     "fundConcretization:write",
     * })
     * @ORM\Column(type="text", length=65535, nullable=true)
     */
    private $description;
    /**
     * @var Fund
     *
     * @Groups({
     *     "fundConcretization:read",
     *     "fundConcretization:create",
     * })
     * @ORM\ManyToOne(targetEntity="Fund", inversedBy="concretizations")
     * @ORM\JoinColumn(nullable=false)
     */
    private $fund;
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
    //endregion

    //region Fund
    /**
     * @var int|null
     *
     * @Groups({
     *     "fund:read",
     *     "fundConcretization:read",
     *     "fundConcretization:write",
     * })
     * @ORM\Column(type="smallint", nullable=true, options={"unsigned": true})
     */
    private $maxLength;
    /**
     * @var string
     *
     * @Groups({
     *     "fund:read",
     *     "fundConcretization:read",
     *     "fundConcretization:write",
     * })
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    private $question;

    public function getDescription(): ?string
    {
        return $this->description;
    }
    //endregion

    //region Image

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

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

    //region MaxLength

    public function getImage(): ?ConcretizationImage
    {
        return $this->image;
    }

    public function setImage(?ConcretizationImage $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }
    //endregion

    //region Question

    public function setMaxLength(?int $maxLength): self
    {
        $this->maxLength = $maxLength;

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
