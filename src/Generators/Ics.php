<?php

declare(strict_types=1);

namespace Spatie\CalendarLinks\Generators;

use Spatie\CalendarLinks\Exceptions\InvalidLink;
use Spatie\CalendarLinks\Generator;
use Spatie\CalendarLinks\Link;

/**
 * @api
 * @see https://icalendar.org/RFC-Specifications/iCalendar-RFC-5545/
 * @psalm-type IcsOptions = array{UID?: string, URL?: string, PRODID?: string, DTSTAMP?: \DateTimeInterface, REMINDER?: array{DESCRIPTION?: string, TIME?: \DateTimeInterface}, TRANSP?: 'OPAQUE'|'TRANSPARENT', CLASS?: 'PUBLIC'|'PRIVATE'|'CONFIDENTIAL', RRULE?: string, X-MICROSOFT-CDO-BUSYSTATUS?: 'FREE'|'TENTATIVE'|'BUSY'|'OOF'}
 * @psalm-type IcsPresentationOptions = array{format?: self::FORMAT_*}
 */
class Ics implements Generator
{
    public const string FORMAT_HTML = 'html';
    public const string FORMAT_FILE = 'file';

    /** @see https://www.php.net/manual/en/function.date.php */
    protected string $dateFormat = 'Ymd';

    protected string $dateTimeFormat = 'Ymd\THis\Z';

    /**
     * A local time, without the Z suffix that marks a UTC value. Used when a TZID names the zone instead.
     * @see https://www.php.net/manual/en/function.date.php
     */
    private const string LOCAL_DATETIME_FORMAT = 'Ymd\THis';

    /**
     * A content line SHOULD NOT be longer than 75 octets, the line break excluded.
     * @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.1
     */
    private const int MAX_CONTENT_LINE_OCTETS = 75;

    /**
     * Values here have been through the constructor's guards, so the four properties written verbatim
     * carry no line break and the enumerated ones carry a known token. That invariant only covers what
     * the constructor was given: a subclass that assigns to this property afterwards is responsible for
     * whatever it puts in, since nothing revalidates it before generate() writes it to the file.
     *
     * @psalm-var IcsOptions
     */
    protected array $options = [];

    /** @psalm-var IcsPresentationOptions */
    protected array $presentationOptions = [];

    /**
     * Option values are validated here rather than in generate(), so that a value the calendar cannot
     * represent is rejected where it enters the library and the stack trace points at the caller that
     * supplied it, instead of at whatever renders the link later on.
     *
     * @param IcsOptions $options Optional ICS properties and components
     * @param IcsPresentationOptions $presentationOptions
     * @throws InvalidLink When an option value cannot be written to the calendar.
     */
    public function __construct(array $options = [], array $presentationOptions = [])
    {
        // The IcsOptions type is a docblock, so it binds static analysis and nothing else. A DTSTAMP
        // that is not a DateTimeInterface is rejected here rather than silently replaced by the
        // default, because a caller who passes one asked for revision semantics and a fallback would
        // hand them a file that looks right and carries the wrong stamp.
        if (isset($options['DTSTAMP']) && ! $options['DTSTAMP'] instanceof \DateTimeInterface) {
            throw InvalidLink::invalidDateTimeOption('DTSTAMP', $options['DTSTAMP']);
        }

        $options = $this->guardAgainstUnwritableValues($options);
        $options = $this->guardAgainstUnsupportedTokens($options);

        $this->options = $options;
        $this->presentationOptions = $presentationOptions;
    }

    /**
     * A URL is a URI and a RRULE is a RECUR, so neither can go through the TEXT escaping of
     * escapeString(): they are written to the calendar as they are given. A line break in one would end
     * the property and start another, letting a caller-supplied value inject arbitrary content into the
     * file, and a lenient parser accepts a bare LF as a line ending, so CR and LF are both rejected.
     *
     * UID and PRODID are TEXT values, which generate() escapes like any other, so a line break in them
     * becomes the \n escape and cannot start a line. They are only stringified here.
     *
     * @param IcsOptions $options
     * @return IcsOptions The options, with each checked value replaced by the string that was checked.
     * @throws InvalidLink
     * @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.1
     */
    private function guardAgainstUnwritableValues(array $options): array
    {
        foreach (['UID', 'PRODID', 'URL', 'RRULE'] as $property) {
            if (! isset($options[$property])) {
                continue;
            }

            $value = $this->asWritten($property, $options[$property]);

            if (in_array($property, ['URL', 'RRULE'], true) && strpbrk($value, "\r\n") !== false) {
                throw InvalidLink::lineBreakInIcsProperty($property);
            }

            $options[$property] = $value;
        }

        return $options;
    }

    /**
     * TRANSP, CLASS and the Microsoft busy status take an enumerated token rather than TEXT, and the
     * token is written to the calendar as is. The Psalm types document the allowed tokens, but nothing
     * enforces them once the value comes from outside a static analyser's reach.
     *
     * @param IcsOptions $options
     * @return IcsOptions The options, with each checked value replaced by the token that was checked.
     * @throws InvalidLink
     */
    private function guardAgainstUnsupportedTokens(array $options): array
    {
        // @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.8.2.7
        if (isset($options['TRANSP'])) {
            $options['TRANSP'] = $this->allowedToken('TRANSP', $options['TRANSP'], ['OPAQUE', 'TRANSPARENT']);
        }

        // @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.8.1.3
        if (isset($options['CLASS'])) {
            $options['CLASS'] = $this->allowedToken('CLASS', $options['CLASS'], ['PUBLIC', 'PRIVATE', 'CONFIDENTIAL']);
        }

        // @see https://learn.microsoft.com/en-us/openspecs/exchange_server_protocols/ms-oxcical/
        if (isset($options['X-MICROSOFT-CDO-BUSYSTATUS'])) {
            $options['X-MICROSOFT-CDO-BUSYSTATUS'] = $this->allowedToken(
                'X-MICROSOFT-CDO-BUSYSTATUS',
                $options['X-MICROSOFT-CDO-BUSYSTATUS'],
                ['FREE', 'TENTATIVE', 'BUSY', 'OOF']
            );
        }

        return $options;
    }

    /**
     * @template TToken of string
     * @param mixed $value
     * @param non-empty-list<TToken> $allowedTokens
     * @return TToken
     * @throws InvalidLink
     */
    private function allowedToken(string $property, mixed $value, array $allowedTokens): string
    {
        $token = $this->asWritten($property, $value);

        // An enumerated property value is case-insensitive, so a lowercase token is as valid as any.
        // The upper-cased spelling is the one written to the file, whatever the caller passed.
        // @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.1
        $normalized = strtoupper($token);

        foreach ($allowedTokens as $allowedToken) {
            if ($normalized === $allowedToken) {
                return $allowedToken;
            }
        }

        throw InvalidLink::unsupportedIcsPropertyValue($property, $token, $allowedTokens);
    }

    /**
     * The string the file will actually receive. generate() builds its content lines by concatenation,
     * which stringifies whatever it is given: an integer, or an object with a __toString(). Checking
     * the value as it was passed would therefore miss a Stringable that hands over a line break or an
     * unknown token, so the guards check this instead, and keep the result. Calling __toString() once
     * and storing what it returned also stops a mutable object from answering differently the second
     * time, when generate() would otherwise call it again.
     *
     * A value with no faithful string form is refused rather than cast, so that the same mistake fails
     * the same way whatever was passed. Casting an array yields the word `Array` and a PHP warning, and
     * casting an object without a __toString() raises a raw PHP Error, neither of which tells the caller
     * what this library expected. Floats and bools are left out on purpose as well: a float's string
     * form follows the `precision` ini setting and INF and NAN come out as words, while `false` casts to
     * an empty string.
     *
     * @param mixed $value
     * @throws InvalidLink
     */
    private function asWritten(string $property, mixed $value): string
    {
        if (is_string($value) || is_int($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        throw InvalidLink::invalidStringOption($property, $value);
    }

    /** @inheritDoc */
    #[\Override]
    public function generate(Link $link): string
    {
        $url = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0', // @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.7.4
            // PRODID and UID are both TEXT values, so they are escaped like SUMMARY and DESCRIPTION.
            // @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.7.3
            'PRODID:'.$this->escapeString($this->options['PRODID'] ?? 'Spatie calendar-links'),
            ...$this->additionalCalendarProperties($link),
            'BEGIN:VEVENT',
            // @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.8.4.7
            'UID:'.$this->escapeString($this->options['UID'] ?? $this->generateEventUid($link)),
            'SUMMARY:'.$this->escapeString($link->title),
        ];

        // DTSTAMP records when the event information was last revised. It defaults to the event start
        // rather than the moment of generation, on purpose: the same Link then always produces the same
        // file, which keeps the output cacheable and snapshot tests stable. Pass a DTSTAMP option to get
        // revision semantics instead. Either way the value is written as a UTC date-time, never as a bare
        // date, including for an all-day event.
        // @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.8.7.2
        $dateStamp = $this->options['DTSTAMP'] ?? $link->from;
        $url[] = 'DTSTAMP:'.gmdate($this->dateTimeFormat, $dateStamp->getTimestamp());

        if ($link->allDay) {
            $url[] = 'DTSTART;VALUE=DATE:'.$link->from->format($this->dateFormat);
            $url[] = 'DURATION:P'.(max(1, (int) $link->from->diff($link->to)->days)).'D';
        } elseif ($link->hasDistinctTimezones()) {
            // Both endpoints are written as local times, each named by its own TZID, so the file
            // shows the event's own zones rather than flattening them to UTC.
            // @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.2.19
            $url[] = 'DTSTART;TZID='.$link->fromTimezone->getName().':'.$link->from->format(self::LOCAL_DATETIME_FORMAT);
            $url[] = 'DTEND;TZID='.$link->toTimezone->getName().':'.$link->to->setTimezone($link->toTimezone)->format(self::LOCAL_DATETIME_FORMAT);
        } else {
            $url[] = 'DTSTART:'.gmdate($this->dateTimeFormat, $link->from->getTimestamp());
            $url[] = 'DTEND:'.gmdate($this->dateTimeFormat, $link->to->getTimestamp());
        }

        // A RECUR value is structured data, so its semicolons and commas are separators
        // and must not go through the TEXT escaping of escapeString().
        // @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.8.5.3
        if (isset($this->options['RRULE'])) {
            $url[] = 'RRULE:'.$this->options['RRULE'];
        }

        if ($link->description !== '') {
            $url[] = 'DESCRIPTION:'.$this->escapeString(strip_tags($link->description));
        }
        if ($link->address !== '') {
            $url[] = 'LOCATION:'.$this->escapeString($link->address);
        }

        foreach ($link->guests as $guest) {
            $role = $guest['optional'] ? 'OPT-PARTICIPANT' : 'REQ-PARTICIPANT';
            $url[] = 'ATTENDEE;ROLE='.$role.':mailto:'.$this->escapeCalendarAddress($guest['email']);
        }

        // TRANSP, CLASS and the Microsoft busy status all take an enumerated token rather than TEXT,
        // so they are emitted verbatim. The constructor has already checked them against their token lists.
        // @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.8.2.7
        if (isset($this->options['TRANSP'])) {
            $url[] = 'TRANSP:'.$this->options['TRANSP'];
        }

        // @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.8.1.3
        if (isset($this->options['CLASS'])) {
            $url[] = 'CLASS:'.$this->options['CLASS'];
        }

        // TRANSP only tells busy from free. Outlook needs this extension to show tentative or out of office.
        // @see https://learn.microsoft.com/en-us/openspecs/exchange_server_protocols/ms-oxcical/
        if (isset($this->options['X-MICROSOFT-CDO-BUSYSTATUS'])) {
            $url[] = 'X-MICROSOFT-CDO-BUSYSTATUS:'.$this->options['X-MICROSOFT-CDO-BUSYSTATUS'];
        }

        if (isset($this->options['URL'])) {
            $url[] = 'URL;VALUE=URI:'.$this->options['URL'];
        }

        $url = [...$url, ...$this->additionalEventProperties($link)];

        // The VALARM component must come last: RFC 5545 requires all VEVENT properties to precede
        // any nested component.
        // @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.6.1
        if (is_array($this->options['REMINDER'] ?? null)) {
            $url = [...$url, ...$this->generateAlertComponent($link)];
        }

        $url[] = 'END:VEVENT';
        $url[] = 'END:VCALENDAR';

        $format = $this->presentationOptions['format'] ?? self::FORMAT_HTML;

        return match ($format) {
            'file' => $this->buildFile($url),
            default => $this->buildLink($url),
        };
    }

    /**
     * @param non-empty-list<string> $propertiesAndComponents
     * @return non-empty-string
     */
    protected function buildLink(array $propertiesAndComponents): string
    {
        // utf-8 is the name registered with IANA, utf8 is not an alias of it.
        // @see https://www.iana.org/assignments/character-sets/character-sets.xhtml
        return 'data:text/calendar;charset=utf-8;base64,'.base64_encode($this->serializeContentLines($propertiesAndComponents));
    }

    /**
     * @param non-empty-list<string> $propertiesAndComponents
     * @return non-empty-string
     */
    protected function buildFile(array $propertiesAndComponents): string
    {
        return $this->serializeContentLines($propertiesAndComponents);
    }

    /**
     * Every content line ends in CRLF, the last one included, and lines that exceed the octet
     * limit are folded so that transports which wrap long lines (email above all, capped at
     * 998 octets by RFC 5322) cannot corrupt a value.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.1
     *
     * @param non-empty-list<string> $propertiesAndComponents
     * @return non-empty-string
     */
    protected function serializeContentLines(array $propertiesAndComponents): string
    {
        return implode("\r\n", array_map($this->foldContentLine(...), $propertiesAndComponents))."\r\n";
    }

    /**
     * Splits an over long content line into a folded representation: CRLF followed by a single
     * space, which an unfolding parser strips to rebuild the original value. The space belongs to
     * the octet budget of the line it opens, and a multibyte UTF-8 sequence is never split, so a
     * boundary landing inside one is moved back to the start of that character.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.1
     * @see https://datatracker.ietf.org/doc/html/rfc3629#section-3
     */
    protected function foldContentLine(string $contentLine): string
    {
        $length = strlen($contentLine);

        if ($length <= self::MAX_CONTENT_LINE_OCTETS) {
            return $contentLine;
        }

        $offset = 0;
        $budget = self::MAX_CONTENT_LINE_OCTETS;
        $folded = [];

        while ($offset < $length) {
            $take = min($budget, $length - $offset);

            // A UTF-8 continuation byte matches 10xxxxxx, so while the next line would start on one
            // the boundary sits inside a character. Never drop below one octet, or nothing advances.
            while ($take > 1 && $offset + $take < $length && (ord($contentLine[$offset + $take]) & 0xC0) === 0x80) {
                $take--;
            }

            $folded[] = ($offset === 0 ? '' : ' ').substr($contentLine, $offset, $take);
            $offset += $take;
            $budget = self::MAX_CONTENT_LINE_OCTETS - 1; // the leading space of a folded line spends an octet
        }

        return implode("\r\n", $folded);
    }

    /** @see https://tools.ietf.org/html/rfc5545.html#section-3.3.11 */
    protected function escapeString(string $field): string
    {
        $escaped = str_replace(
            ['\\', ';', ',', "\r\n", "\r", "\n"],
            ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'],
            $field
        );

        // A TEXT value admits no control character other than HTAB, and the CR and LF that did occur
        // have become the \n escape just above, so whatever is left in these ranges is dropped.
        // @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.3.11
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $escaped) ?? $escaped;
    }

    /**
     * An ATTENDEE value is a CAL-ADDRESS, which is a URI rather than TEXT, so the backslash escaping
     * of escapeString() does not apply to it. Characters that are legal in an email address but would
     * change the meaning of the mailto URI are percent encoded instead.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.3.3
     * @see https://datatracker.ietf.org/doc/html/rfc6068#section-2
     */
    protected function escapeCalendarAddress(string $email): string
    {
        return str_replace(
            ['%', '&', '?', '=', '/', '#'],
            ['%25', '%26', '%3F', '%3D', '%2F', '%23'],
            $email
        );
    }

    /** @see https://tools.ietf.org/html/rfc5545#section-3.8.4.7 */
    protected function generateEventUid(Link $link): string
    {
        return md5(sprintf(
            '%s%s%s%s',
            $link->from->format(\DateTimeInterface::ATOM),
            $link->to->format(\DateTimeInterface::ATOM),
            $link->title,
            $link->address
        ));
    }

    /**
     * Extension point: extra properties for the VCALENDAR level, emitted before BEGIN:VEVENT
     * (e.g. METHOD or X-WR-CALNAME). One full content line per entry, already escaped:
     * TEXT values should go through escapeString().
     *
     * @return list<string>
     */
    protected function additionalCalendarProperties(Link $link): array
    {
        return [];
    }

    /**
     * Extension point: extra properties for the VEVENT component, emitted after the built in
     * properties and before any VALARM component (e.g. CATEGORIES, STATUS, ORGANIZER or any
     * X- property). One full content line per entry, already escaped: TEXT values should go
     * through escapeString(). Returning a nested component here breaks the ordering that
     * RFC 5545 requires, so keep it to properties.
     *
     * @return list<string>
     */
    protected function additionalEventProperties(Link $link): array
    {
        return [];
    }

    /**
     * @param \Spatie\CalendarLinks\Link $link
     * @return list<string>
     */
    protected function generateAlertComponent(Link $link): array
    {
        // A VALARM DESCRIPTION is a TEXT value, so a custom one needs the same escaping as the default.
        // @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.6.6
        $description = $this->options['REMINDER']['DESCRIPTION'] ?? null;
        $description = is_string($description)
            ? $this->escapeString($description)
            : 'Reminder: '.$this->escapeString($link->title);

        $trigger = 'TRIGGER:-PT15M';
        if (($reminderTime = $this->options['REMINDER']['TIME'] ?? null) instanceof \DateTimeInterface) {
            $trigger = 'TRIGGER;VALUE=DATE-TIME:'.gmdate($this->dateTimeFormat, $reminderTime->getTimestamp());
        }

        $alarmComponent = [];
        $alarmComponent[] = 'BEGIN:VALARM';
        $alarmComponent[] = 'ACTION:DISPLAY';
        $alarmComponent[] = 'DESCRIPTION:'.$description;
        $alarmComponent[] = $trigger;
        $alarmComponent[] = 'END:VALARM';

        return $alarmComponent;
    }
}
