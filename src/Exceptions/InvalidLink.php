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
        trigger_error(sprintf('Method %s::%s is deprecated, please use %s::negativeDateRange method instead', self::class, __METHOD__, self::class), \E_USER_DEPRECATED);

        return new self("TO time (`{$to->format(self::DATETIME_FORMAT)}`) must be greater than FROM time (`{$from->format(self::DATETIME_FORMAT)}`)");
    }

    public static function negativeDateRange(DateTimeInterface $from, DateTimeInterface $to): self
    {
        return new self("TO time (`{$to->format(self::DATETIME_FORMAT)}`) must be greater than FROM time (`{$from->format(self::DATETIME_FORMAT)}`)");
    }
}
