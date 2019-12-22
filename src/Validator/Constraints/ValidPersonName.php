<?php
declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\RegexValidator;

/**
 * Exists because it is impossible to escape this regex in an annotation...
 *
 * @Annotation
 */
class ValidPersonName extends Regex
{
    public $match   = false;
    public $message = "The name contains invalid characters.";

    public function __construct($options = null)
    {
        $options['pattern'] = '/[\d\\\\\/,;:_~@?!$%&§=#+"()<>[\]{}]/u';
        parent::__construct($options);
    }

    public function getTargets()
    {
        return self::PROPERTY_CONSTRAINT;
    }

    public function validatedBy()
    {
        return RegexValidator::class;
    }
}
