<?php

namespace app\entities\forum;

use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

class DateTimeTzMicrosecondsType extends Type {
    /**
     * {@inheritDoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform) {
        return "TIMESTAMP(6) WITH TIME ZONE";
    }

    /**
     * {@inheritDoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform) {
        if ($value === null || $value instanceof DateTimeInterface) {
            return $value;
        }

        $val = DateTime::createFromFormat("Y-m-d H:i:s.uO", $value);
        if ($val === false) {
            $val = DateTime::createFromFormat("Y-m-d H:i:sO", $value);
            if ($val === false) {
                throw ConversionException::conversionFailedFormat(
                    $value,
                    $this->getName(),
                    "Y-m-d H:i:s.uO"
                );
            }
        }

        return $val;
    }

    /**
     * {@inheritDoc}
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform) {
        if ($value === null) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format("Y-m-d H:i:s.uO");
        }

        throw ConversionException::conversionFailedInvalidType(
            $value,
            $this->getName(),
            ['null', 'DateTime']
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getName() {
        return "datetimetzmicro";
    }
}
