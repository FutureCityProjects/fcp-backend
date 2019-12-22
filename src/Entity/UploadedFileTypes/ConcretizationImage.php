<?php
declare(strict_types=1);

namespace App\Entity\UploadedFileTypes;

use App\Entity\AbstractUploadedFile;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

/**
 * @ORM\Entity
 */
class ConcretizationImage extends AbstractUploadedFile
{
    /**
     * NOTE: This is not a mapped field of entity metadata, just a simple property.
     *
     * @Vich\UploadableField(mapping="public_file", fileNameProperty="name", size="size", mimeType="mimeType", originalName="originalName", dimensions="dimensions")
     *
     * @var File
     */
    private $file;
}
