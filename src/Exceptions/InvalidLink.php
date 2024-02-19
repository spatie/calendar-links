<?php

declare(strict_types=1);

namespace Spatie\CalendarLinks\Exceptions;

use DateTimeInterface;
use InvalidArgumentException;

class InvalidLink extends InvalidArgumentException
{
    private const DATETIME_FORMAT = 'Y-m-d H:i:s';

    public static function negativeDateRange(DateTimeInterface $from, DateTimeInterface $to): self
    {
        return new self("TO time (`{$to->format(self::DATETIME_FORMAT)}`) must be greater than FROM time (`{$from->format(self::DATETIME_FORMAT)}`)");
    }
}
