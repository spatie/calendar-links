<?php

namespace Spatie\CalendarLinks\Exceptions;

use Exception;

class InvalidLink extends Exception
{
    public static function invalidDateRange(): self
    {
        return new self('`$to` must be greater than `$from`');
    }
}
