<?php
declare(strict_types=1);

namespace App\Doctrine\DBAL\Types;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateTimeImmutableType;

class UTCDateTimeType extends DateTimeImmutableType
{
    /**
     * @var \DateTimezone
     */
    private static $utcDateTimezone;

    /**
     * @param mixed $value
     * @param AbstractPlatform $platform
     *
     * @return mixed
     * @throws ConversionException
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if (null === $value) {
            return $value;
        }

        if (!($value instanceof DateTimeInterface)) {
            throw ConversionException::conversionFailedInvalidType($value, $this->getName(), [
                'DateTime', 'DateTimeImmutable', 'DateTimeInterface'
            ]);
        }

        if (! ($value instanceof DateTimeImmutable)) {
            $value = DateTimeImmutable::createFromMutable($value);
        }

        self::$utcDateTimezone = self::$utcDateTimezone ?: new \DateTimeZone('UTC');

        if ('UTC' !== $value->getTimezone()->getName()) {
            $value = $value->setTimezone(self::$utcDateTimezone);
        }

        return $value->format($platform->getDateTimeFormatString());
    }

    /**
     * @param mixed $value
     * @param AbstractPlatform $platform
     *
     * @return DateTimeImmutable|null
     * @throws ConversionException
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        if (null === $value) {
            return $value;
        }

        self::$utcDateTimezone = self::$utcDateTimezone ?: new DateTimeZone('UTC');

        $converted = DateTimeImmutable::createFromFormat(
            $platform->getDateTimeFormatString(),
            $value,
            self::$utcDateTimezone
        );

        if (!$converted) {
            throw ConversionException::conversionFailedFormat(
                $value,
                $this->getName(),
                $platform->getDateTimeFormatString()
            );
        }

        return $converted;
    }
}
