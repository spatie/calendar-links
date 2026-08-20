<?php

declare(strict_types=1);

namespace Spatie\CalendarLinks\Exceptions;

use DateTimeInterface;
use InvalidArgumentException;

/** @api */
class InvalidLink extends InvalidArgumentException
{
    private const string DATETIME_FORMAT = 'Y-m-d H:i:s e';

    public static function negativeDateRange(DateTimeInterface $from, DateTimeInterface $to): self
    {
        return new self("TO time (`{$to->format(self::DATETIME_FORMAT)}`) must not be earlier than FROM time (`{$from->format(self::DATETIME_FORMAT)}`)");
    }

    public static function invalidGuestEmail(string $email): self
    {
        return new self("Guest email address (`{$email}`) is invalid. Pass a plain email address, without a display name or a quoted local part.");
    }

    /** @param mixed $given The value the caller passed, used only to name its type in the message. */
    public static function invalidDateTimeOption(string $option, mixed $given): self
    {
        return new self("The `{$option}` option must be a DateTimeInterface, `".get_debug_type($given).'` given.');
    }
}
