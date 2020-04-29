<?php

namespace Spatie\CalendarLinks\Exceptions;

use DateTimeInterface;
use Exception;

class InvalidLink extends Exception
{
    public static function invalidDateRange(DateTimeInterface $to, DateTimeInterface $from): self
    {
        return new self("`{$to->format('YMD His')}` must be greater than `{$from->format('YMD His')}`");
    }
}
