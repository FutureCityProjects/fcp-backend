<?php
declare(strict_types=1);

namespace App\Validator;

abstract class NormalizerHelper
{
    /**
     * Removes HTML tags from the given value and trims the result.
     * Used to check for empty values for entity fields that allow HTML
     *
     * @param string $content
     * @return string
     */
    public static function stripHtml(string $content): string
    {
        return trim(strip_tags($content));
    }
}
