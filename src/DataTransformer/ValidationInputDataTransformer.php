<?php
declare(strict_types=1);

namespace App\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use ApiPlatform\Core\Validator\ValidatorInterface;
use App\Dto\ValidationInput;
use App\Entity\Validation;

/**
 * Handles setting the creator.
 */
class ValidationInputDataTransformer implements DataTransformerInterface
{
    /**
     * @var ValidatorInterface
     */
    private $validator;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    /**
     * {@inheritdoc}
     *
     * @param ValidationInput $data
     * @return Validation
     */
    public function transform($data, string $to, array $context = [])
    {
        // this evaluates all constraint annotations on the DTO
        $this->validator->validate($data);

        /** @var ?Validation $validation */
        $validation = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE]
            ?? null;

        // do nothing, we just want to validate the input and provide the
        // validation object for the ValidationConfirmAction

        return $validation;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof Validation) {
            return false;
        }

        return Validation::class === $to && null !== ($context['input']['class'] ?? null);
    }
}
