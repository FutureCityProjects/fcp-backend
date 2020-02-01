<?php
declare(strict_types=1);

namespace App\Validator;

use App\Entity\FundApplication;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class FundApplicationValidator
{
    public static function validateConcretizations($value, ExecutionContextInterface $context, $payload)
    {
        /** @var FundApplication $application */
        $application = $context->getObject();

        if (!$application instanceof FundApplication) {
            throw new UnexpectedTypeException($application, FundApplication::class);
        }

        if (empty($value) || !$application->getFund()) {
            return;
        }

        $concretizations = $application->getFund()->getConcretizations()->toArray();

        foreach($value as $index => $text) {
            $found = false;
            foreach ($concretizations as $concretization) {
                if ($index != $concretization->getId()) {
                    continue;
                }

                $found = true;
                $normalized = NormalizerHelper::stripHtml($text);

                if (mb_strlen($normalized) > $concretization->getMaxLength()) {
                    $context->buildViolation('validate.general.tooLong')
                        ->atPath("[$index]")
                        ->addViolation();
                }
            }

            if (!$found) {
                $context->buildViolation('validate.fundApplication.invalidConcretization')
                    ->atPath("[$index]")
                    ->addViolation();
                continue;
            }
        }
    }
}
