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

    /** @param mixed $given The value the caller passed, used only to name its type in the message. */
    public static function invalidStringOption(string $option, mixed $given): self
    {
        return new self("The `{$option}` option must be a string, an integer or a Stringable, `".get_debug_type($given).'` given.');
    }

    /**
     * The value is deliberately left out of the message: it contains a line break, which would spread
     * the exception message over several lines of a log just as it would over several lines of a calendar.
     */
    public static function lineBreakInIcsProperty(string $property): self
    {
        return new self("ICS property (`{$property}`) must not contain a CR or an LF character. Its value is written to the calendar as is, so a line break would inject additional properties into it.");
    }

    /**
     * CR and LF are dropped from the reported value for the same reason lineBreakInIcsProperty() leaves
     * its value out: it would spread a forged line over several lines of a log.
     *
     * @param non-empty-list<string> $allowedValues
     */
    public static function unsupportedIcsPropertyValue(string $property, string $value, array $allowedValues): self
    {
        $value = str_replace(["\r", "\n"], '', $value);
        $allowed = implode('`, `', $allowedValues);

        return new self("ICS property (`{$property}`) value (`{$value}`) is invalid. Pass one of `{$allowed}`.");
    }
}
