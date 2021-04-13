<?php

namespace Spatie\CalendarLinks\Exceptions;

use DateTimeInterface;
use InvalidArgumentException;

class InvalidLink extends InvalidArgumentException
{
    private const DATETIME_FORMAT = 'Y-m-d H:i:s';

    /** @deprecated Will be removed in 2.0, please use {@see InvalidLink::negativeDateRange} instead */
    public static function invalidDateRange(DateTimeInterface $to, DateTimeInterface $from): self
    {
        return new self("TO time (`{$to->format(self::DATETIME_FORMAT)}`) must be greater than FROM time (`{$from->format(self::DATETIME_FORMAT)}`)");
    }

    public static function negativeDateRange(DateTimeInterface $from, DateTimeInterface $to): self
    {
        return new self("TO time (`{$to->format(self::DATETIME_FORMAT)}`) must be greater than FROM time (`{$from->format(self::DATETIME_FORMAT)}`)");
    }
}
