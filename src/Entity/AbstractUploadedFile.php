<?php
declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\AutoincrementId;
use Doctrine\ORM\Mapping as ORM;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

/**
 * @ORM\Entity()
 * @ORM\Table(name="uploaded_file", indexes={
 *     @ORM\Index(name="type_idx", columns={"type"})
 * })
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="type", type="string", length=50)
 * @ORM\DiscriminatorMap({
 *     "concretization_image" = "App\Entity\UploadedFileTypes\ConcretizationImage",
 *     "fund_logo" = "App\Entity\UploadedFileTypes\FundLogo",
 *     "process_logo" = "App\Entity\UploadedFileTypes\ProcessLogo",
 *     "project_picture" = "App\Entity\UploadedFileTypes\ProjectPicture",
 *     "project_visualization" = "App\Entity\UploadedFileTypes\ProjectVisualization"
 * })
 * @Vich\Uploadable
 */
abstract class AbstractUploadedFile
{
    use AutoincrementId;

    //region Name
    /**
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    protected $name;
    /**
     * @ORM\Column(nullable=false)
     */
    protected $originalName;
    /**
     * @var string
     *
     * @ORM\Column(type="string", length=50, nullable=false)
     */
    protected $mimeType;
    //endregion

    //region OriginalName
    /**
     * @ORM\Column(type="integer", nullable=false, options={"unsigned": true})
     */
    protected $size;
    /**
     * @var array|null
     * @ORM\Column(type="small_json", length=50, nullable=true)
     */
    protected $dimensions;

    public function getName(): ?string
    {
        return $this->name;
    }
    //endregion

    //region MimeType

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getOriginalName(): ?string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): self
    {
        $this->originalName = $originalName;

        return $this;
    }
    //endregion

    //region Size

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }
    //endregion

    //region Dimensions

    public function setSize(int $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function getDimensions(): ?array
    {
        return $this->dimensions;
    }

    public function setDimensions(?array $dimensions): self
    {
        $this->dimensions = $dimensions;

        return $this;
    }
    //endregion
}
