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
     * How far past the observance window the zone is read for a repeating pattern, and how many
     * onsets have to agree before one is written as a rule. Six years of a twice yearly zone offer
     * six onsets of each kind, so three is a pattern rather than a coincidence while still leaving
     * room for a zone that changes its rules partway through.
     */
    private const int RULE_PROBE_YEARS = 6;

    private const int MIN_ONSETS_TO_CONFIRM_A_RULE = 3;

    /** Long enough that RULE_PROBE_YEARS spans the whole years it names, leap years included. */
    private const int SECONDS_PER_YEAR = 366 * 24 * 60 * 60;

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
        $options = $this->guardAgainstUnusableReminder($options);

        $this->guardAgainstUnsupportedFormat($presentationOptions);

        $this->options = $options;
        $this->presentationOptions = $presentationOptions;
    }

    /**
     * The alarm reads its two values back at generation time and falls back to the default alarm for
     * anything it cannot use, which would hand a caller who misspelled a type the reminder they did
     * not ask for, fifteen minutes before the event, with nothing said about it. The same mistake is
     * caught here instead, for the same reason DTSTAMP is.
     *
     * @param IcsOptions $options
     * @return IcsOptions The options, with a checked DESCRIPTION replaced by the string that was checked.
     * @throws InvalidLink
     */
    private function guardAgainstUnusableReminder(array $options): array
    {
        if (! isset($options['REMINDER'])) {
            return $options;
        }

        /** @psalm-suppress DocblockTypeContradiction The docblock type binds static analysis and nothing else. */
        if (! is_array($options['REMINDER'])) {
            throw InvalidLink::invalidArrayOption('REMINDER', $options['REMINDER']);
        }

        if (isset($options['REMINDER']['TIME']) && ! $options['REMINDER']['TIME'] instanceof \DateTimeInterface) {
            throw InvalidLink::invalidDateTimeOption('REMINDER.TIME', $options['REMINDER']['TIME']);
        }

        if (isset($options['REMINDER']['DESCRIPTION'])) {
            // A VALARM DESCRIPTION is a TEXT value, so escapeString() handles a line break in it and
            // only the faithfulness of the string form is in question here.
            $options['REMINDER']['DESCRIPTION'] = $this->asWritten('REMINDER.DESCRIPTION', $options['REMINDER']['DESCRIPTION']);
        }

        return $options;
    }

    /**
     * An unknown format used to fall through to the data URI, so a caller who asked for `FILE` in the
     * wrong case was handed a link and had to work out from the output that they had not got a file.
     *
     * @param array<string, mixed> $presentationOptions
     * @throws InvalidLink
     */
    private function guardAgainstUnsupportedFormat(array $presentationOptions): void
    {
        if (! isset($presentationOptions['format'])) {
            return;
        }

        $format = $this->asWritten('format', $presentationOptions['format']);

        if (! in_array($format, [self::FORMAT_HTML, self::FORMAT_FILE], true)) {
            throw InvalidLink::unsupportedIcsPropertyValue('format', $format, [self::FORMAT_HTML, self::FORMAT_FILE]);
        }
    }

    /**
     * A URL is a URI and a RRULE is a RECUR, so neither can go through the TEXT escaping of
     * escapeString(): they are written to the calendar as they are given. A line break in one would end
     * the property and start another, letting a caller-supplied value inject arbitrary content into the
     * file, and a lenient parser accepts a bare LF as a line ending, so CR and LF are both rejected.
     * Neither value type admits any other control character either, and escapeString() is not there to
     * drop them, so those are turned down rather than written out to make an unparsable file.
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

            if (in_array($property, ['URL', 'RRULE'], true)) {
                if (strpbrk($value, "\r\n") !== false) {
                    throw InvalidLink::lineBreakInIcsProperty($property);
                }

                if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
                    throw InvalidLink::controlCharacterInIcsProperty($property);
                }
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
        ];

        // Properties precede components at the VCALENDAR level, which is why this sits after the
        // calendar properties and before the event.
        // @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.6.5
        if ($this->shouldDefineTimezones($link)) {
            $url = [...$url, ...$this->generateTimezoneComponents($link)];
        }

        $url = [
            ...$url,
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
        } elseif ($this->shouldNameTimezones($link)) {
            // Both endpoints are written as local times, each named by its own TZID, so the file shows
            // the event's own zones rather than flattening them to UTC.
            // @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.2.19
            $endTimezone = $this->endTimezone($link);

            $url[] = 'DTSTART;TZID='.$link->fromTimezone->getName().':'.$link->from->format(self::LOCAL_DATETIME_FORMAT);
            $url[] = 'DTEND;TZID='.$endTimezone->getName().':'.$link->to->setTimezone($endTimezone)->format(self::LOCAL_DATETIME_FORMAT);
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

        // The constructor has already turned down anything that is not one of the two formats.
        $format = $this->presentationOptions['format'] ?? self::FORMAT_HTML;

        return match ($format) {
            self::FORMAT_FILE => $this->buildFile($url),
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
     * A control character is percent encoded along with them. guest() rejects one long before it gets
     * here, but $guests is a public property, so an address can also be assigned straight to it without
     * passing that check. A CR or an LF in one would end the ATTENDEE property and start another,
     * letting the address inject arbitrary content into the file.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.3.3
     * @see https://datatracker.ietf.org/doc/html/rfc6068#section-2
     */
    protected function escapeCalendarAddress(string $email): string
    {
        $escaped = str_replace(
            ['%', '&', '?', '=', '/', '#'],
            ['%25', '%26', '%3F', '%3D', '%2F', '%23'],
            $email
        );

        return preg_replace_callback(
            '/[\x00-\x1F\x7F]/',
            static fn (array $match): string => sprintf('%%%02X', ord($match[0])),
            $escaped
        ) ?? $escaped;
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
     * Whether the endpoints are written as local times named by a TZID, rather than as UTC instants.
     *
     * A UTC instant is unambiguous, so it is the better answer for an event that happens once: it
     * needs no VTIMEZONE and no zone database on the reading side. A recurrence is the case it cannot
     * serve. An RRULE repeats the local time of its DTSTART, so a start pinned to UTC repeats in UTC,
     * and every occurrence on the far side of a daylight saving change lands an hour away from the
     * time the event was booked for. Naming the zone is what keeps a weekly 09:00 at 09:00.
     *
     * A recurrence in a zone that never moves its clocks has nothing to drift against, so UTC instants
     * still say the same thing there and are left alone: naming UTC, or a zone that keeps one offset
     * the year round, would only add a VTIMEZONE describing a zone that never changes.
     *
     * An event whose two ends genuinely name different zones is written this way whether it recurs or
     * not, since UTC instants would flatten the pair and lose what the two zones were for.
     *
     * A TZID may only name a zone the client can look up, and a param-value carries no unquoted `:`,
     * so a zone that is only an offset (`+02:00`) would fail to resolve and cut the property value in
     * half. Those events keep the UTC instants. An all-day event has no clock time to place in a zone.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.1
     * @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.8.5.3
     */
    protected function shouldNameTimezones(Link $link): bool
    {
        if ($link->allDay || ! $link->hasResolvableTimezones()) {
            return false;
        }

        return $link->hasDistinctTimezones()
            || (isset($this->options['RRULE']) && $this->observesAChange($link->fromTimezone, $link));
    }

    /**
     * Whether the zone moves its clocks anywhere near the event, over the same stretch the VTIMEZONE
     * would describe. A zone that does not is one offset from end to end, which a UTC instant already
     * carries.
     *
     * generate() asks this through shouldNameTimezones(), which it reaches three times over one link:
     * once to choose how to write the endpoints, and twice more through referencedTimezones(). The
     * answer is a function of the zone and the event's two instants and nothing else, so it is worked
     * out once and kept. Reading a zone's table is the most expensive thing this class does.
     *
     * @var array<string, bool>
     */
    private array $observedChanges = [];

    private function observesAChange(\DateTimeZone $timezone, Link $link): bool
    {
        $windowStart = $link->from->modify('-1 year')->getTimestamp();
        $windowEnd = $link->to->modify('+1 year')->getTimestamp() + self::RULE_PROBE_YEARS * self::SECONDS_PER_YEAR;

        $key = $timezone->getName()."\0".$windowStart."\0".$windowEnd;

        if (! isset($this->observedChanges[$key])) {
            $transitions = $timezone->getTransitions($windowStart, $windowEnd);

            // The first entry is the offset already in force when the stretch opens, not a change.
            $this->observedChanges[$key] = is_array($transitions) && count($transitions) > 1;
        }

        return $this->observedChanges[$key];
    }

    /**
     * The zone DTEND is named with. When the two zones collapse into one, the end zone is a second
     * spelling that generate() has already folded into the start's, so the start's is what the file
     * names: hasResolvableTimezones() never checked the other spelling and it may not resolve at all.
     */
    private function endTimezone(Link $link): \DateTimeZone
    {
        return $link->hasDistinctTimezones() ? $link->toTimezone : $link->fromTimezone;
    }

    /**
     * Whether the file needs VTIMEZONE components at all.
     *
     * A VTIMEZONE is only meaningful when a property in the file references it, so this answers the
     * same question referencedTimezones() does, and an override that narrows one narrows the other
     * with it rather than leaving the file with an orphan component defining a zone nothing names.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.6.5
     */
    protected function shouldDefineTimezones(Link $link): bool
    {
        return $this->referencedTimezones($link) !== [];
    }

    /**
     * Extension point: the zones the generated file names with a TZID parameter, in the order their
     * VTIMEZONE components should appear. Repeated names are collapsed by the caller, so an override
     * is free to list a zone it cannot rule out being there already.
     *
     * @return list<\DateTimeZone>
     */
    protected function referencedTimezones(Link $link): array
    {
        if (! $this->shouldNameTimezones($link)) {
            return [];
        }

        return [$link->fromTimezone, $this->endTimezone($link)];
    }

    /**
     * "An individual VTIMEZONE calendar component MUST be specified for each unique TZID parameter
     * value specified in the iCalendar object." Without them the file is invalid, and a client that
     * does not resolve bare IANA identifiers (older Outlook desktop) reads the endpoints as floating
     * local times, which shifts the event.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.6.5
     * @return list<string>
     */
    protected function generateTimezoneComponents(Link $link): array
    {
        $components = [];
        $definedTimezones = [];

        foreach ($this->referencedTimezones($link) as $timezone) {
            $tzid = $timezone->getName();

            // Unique is per TZID value, not per referencing property, so an event that departs and
            // lands in one zone still gets a single component.
            if (isset($definedTimezones[$tzid])) {
                continue;
            }

            $definedTimezones[$tzid] = true;

            $components = [...$components, ...$this->generateTimezoneComponent($timezone, $link)];
        }

        return $components;
    }

    /**
     * @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.6.5
     * @return list<string>
     */
    protected function generateTimezoneComponent(\DateTimeZone $timezone, Link $link): array
    {
        $component = ['BEGIN:VTIMEZONE'];
        $component[] = 'TZID:'.$timezone->getName();

        foreach ($this->generateTimezoneObservances($timezone, $link) as $observance) {
            $component = [...$component, ...$observance];
        }

        $component[] = 'END:VTIMEZONE';

        return $component;
    }

    /**
     * Every change the zone makes inside a window around the event, in the order it makes them, one
     * observance each. A local time is resolved against the observance with the latest onset at or
     * before it, taken across the whole component, so a plain chronological list is what a parser
     * wants and no change may be left out. Africa/Casablanca suspends its summer time for Ramadan
     * and restores it weeks later, which puts two changes of one kind in a single year at two
     * different offsets, and a component holding only one of each moves the event by an hour.
     *
     * The first entry is the offset already in effect when the window opens rather than a change, so
     * it is written with TZOFFSETFROM equal to TZOFFSETTO. That covers the stretch before the zone's
     * first real change, which the event itself can fall in.
     *
     * The window reaches a year either side of the event, which holds every change the event can be
     * resolved against while keeping the component to a handful of observances. It is measured from
     * the event rather than from today, so the same Link always produces the same file.
     *
     * The window alone would end the component where it ends, and an event that recurs past it would
     * be resolved against the last observance written, which is the wrong offset for part of every
     * later year. So the last observance of each kind carries a yearly recurrence rule when the zone
     * repeats that change on a fixed weekday of a fixed month, which is what annualRecurrenceRules()
     * confirms before writing one.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.6.5
     * @return list<list<string>>
     */
    private function generateTimezoneObservances(\DateTimeZone $timezone, Link $link): array
    {
        $windowStart = $link->from->modify('-1 year')->getTimestamp();
        $windowEnd = $link->to->modify('+1 year')->getTimestamp();
        $transitions = $timezone->getTransitions($windowStart, $windowEnd);

        // A zone named by a bare abbreviation or a fixed offset (EST, CET, +05:30) has no transition
        // table for PHP to return. RFC 5545 still wants at least one subcomponent, so the one offset
        // such a zone has stands in as the state at the window opening and runs through the loop
        // below like any other.
        if ($transitions === false || $transitions === []) {
            $transitions = [[
                'ts' => $windowStart,
                'offset' => $timezone->getOffset($link->from),
                'isdst' => false,
                'abbr' => $link->from->setTimezone($timezone)->format('T'),
            ]];
        }

        // The entry at index 0 is the offset already in force when the window opened rather than a
        // change the zone makes, so a table holding nothing else has no change for a rule to repeat
        // and no reason to read the years beyond the window looking for one.
        $recurrenceRules = count($transitions) > 1
            ? $this->annualRecurrenceRules($timezone, $transitions, $windowEnd)
            : [];

        $observances = [];
        $previousOffset = null;

        foreach ($transitions as $index => $transition) {
            // There is no earlier offset to move away from on the first entry.
            $offsetFrom = $previousOffset ?? $transition['offset'];
            $previousOffset = $transition['offset'];
            $type = $transition['isdst'] ? 'DAYLIGHT' : 'STANDARD';

            $observance = [
                'BEGIN:'.$type,
                // An onset is a local time read against the offset being left behind.
                // @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.8.2.4
                'DTSTART:'.gmdate(self::LOCAL_DATETIME_FORMAT, $transition['ts'] + $offsetFrom),
                'TZOFFSETFROM:'.$this->formatUtcOffset($offsetFrom),
                'TZOFFSETTO:'.$this->formatUtcOffset($transition['offset']),
            ];

            if (isset($recurrenceRules[$index])) {
                $observance[] = $recurrenceRules[$index];
            }

            $observance[] = 'TZNAME:'.$this->escapeString($transition['abbr']);
            $observance[] = 'END:'.$type;

            $observances[] = $observance;
        }

        return $observances;
    }

    /**
     * A yearly RRULE for the last observance of each kind, so the component keeps describing the zone
     * after the window it was built from runs out.
     *
     * A rule is only written for a change the zone demonstrably repeats: the onsets past the window
     * are read as well, and all of them, together with the observance the rule would be attached to,
     * have to fall on the same weekday of the same week of the same month at the same local time.
     * Anything else is left without a rule rather than guessed at. A zone that abolished its daylight
     * saving has no later onset to confirm and correctly keeps a last observance that simply stands,
     * and Africa/Casablanca, which suspends its summer time for Ramadan and restores it weeks later,
     * moves both of its yearly changes and so matches nothing.
     *
     * @param non-empty-list<array{ts: int, offset: int, isdst: bool, ...}> $transitions
     * @return array<int, string> The rule to write, keyed by its observance's index in $transitions.
     * @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.8.5.3
     */
    private function annualRecurrenceRules(\DateTimeZone $timezone, array $transitions, int $windowEnd): array
    {
        $laterOnsets = $this->onsetsAfter($timezone, $windowEnd);

        $rules = [];

        foreach ($this->lastOnsetOfEachKind($transitions) as $isDaylight => $lastOnset) {
            $onsets = [$lastOnset];

            foreach ($laterOnsets as $onset) {
                if ($onset['isdst'] === (bool) $isDaylight) {
                    $onsets[] = $onset;
                }
            }

            if (count($onsets) < self::MIN_ONSETS_TO_CONFIRM_A_RULE) {
                continue;
            }

            $rule = $this->annualRuleFor($onsets);

            if ($rule !== null) {
                $rules[$lastOnset['index']] = $rule;
            }
        }

        return $rules;
    }

    /**
     * The last change of each kind the window holds, keyed by whether it starts daylight saving, with
     * the offset it moves away from and the index of the observance it belongs to.
     *
     * The entry at index 0 is the offset already in force when the window opened rather than a change
     * the zone makes, so it is skipped: a yearly rule has nothing to repeat there.
     *
     * @param non-empty-list<array{ts: int, offset: int, isdst: bool, ...}> $transitions
     * @return array<int, array{ts: int, offsetFrom: int, index: int}>
     */
    private function lastOnsetOfEachKind(array $transitions): array
    {
        $lastOnsets = [];

        foreach ($transitions as $index => $transition) {
            if ($index === 0) {
                continue;
            }

            $lastOnsets[(int) $transition['isdst']] = [
                'ts' => $transition['ts'],
                'offsetFrom' => $transitions[$index - 1]['offset'],
                'index' => $index,
            ];
        }

        return $lastOnsets;
    }

    /**
     * The zone's changes over the years that follow the window, each paired with the offset it moves
     * away from, which is what its onset is a local time in.
     *
     * @return list<array{ts: int, offsetFrom: int, isdst: bool}>
     */
    private function onsetsAfter(\DateTimeZone $timezone, int $from): array
    {
        $transitions = $timezone->getTransitions($from, $from + self::RULE_PROBE_YEARS * self::SECONDS_PER_YEAR);

        if ($transitions === false || $transitions === []) {
            return [];
        }

        $onsets = [];
        $previousOffset = null;

        foreach ($transitions as $transition) {
            // As in the window itself, the first entry is the state at the start rather than a change.
            if ($previousOffset !== null) {
                $onsets[] = ['ts' => $transition['ts'], 'offsetFrom' => $previousOffset, 'isdst' => $transition['isdst']];
            }

            $previousOffset = $transition['offset'];
        }

        return $onsets;
    }

    /**
     * The rule these onsets all follow, or null when they follow none. `BYDAY=-1SU` is preferred over
     * `BYDAY=5SU` whenever every onset is the last of its weekday in the month, since a month with
     * only four of that weekday has no fifth one for the rule to land on.
     *
     * @param non-empty-list<array{ts: int, offsetFrom: int, ...}> $onsets
     */
    private function annualRuleFor(array $onsets): ?string
    {
        $weekdays = ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'];

        $months = [];
        $weekdayNumbers = [];
        $localTimes = [];
        $weeksOfMonth = [];
        $isAlwaysLastOfMonth = true;

        foreach ($onsets as $onset) {
            $local = $onset['ts'] + $onset['offsetFrom'];
            $dayOfMonth = (int) gmdate('j', $local);

            $months[] = (int) gmdate('n', $local);
            $weekdayNumbers[] = (int) gmdate('w', $local);
            $localTimes[] = gmdate('His', $local);
            $weeksOfMonth[] = intdiv($dayOfMonth - 1, 7) + 1;

            $isAlwaysLastOfMonth = $isAlwaysLastOfMonth && $dayOfMonth + 7 > (int) gmdate('t', $local);
        }

        // A yearly rule states one month, one weekday and one time, so onsets that disagree on any of
        // the three follow no rule this can write.
        if (! self::allTheSame($months) || ! self::allTheSame($weekdayNumbers) || ! self::allTheSame($localTimes)) {
            return null;
        }

        $weekOfMonth = match (true) {
            $isAlwaysLastOfMonth => -1,
            self::allTheSame($weeksOfMonth) => $weeksOfMonth[0],
            default => null,
        };

        if ($weekOfMonth === null) {
            return null;
        }

        return 'RRULE:FREQ=YEARLY;BYMONTH='.$months[0].';BYDAY='.$weekOfMonth.$weekdays[$weekdayNumbers[0]];
    }

    /** @param non-empty-list<int|string> $values */
    private static function allTheSame(array $values): bool
    {
        return count(array_unique($values)) === 1;
    }

    /**
     * A UTC offset is signed hours and minutes, with seconds appended only when a zone needs them,
     * which in practice means the local mean times that predate standardised zones.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.3.14
     */
    private function formatUtcOffset(int $offsetInSeconds): string
    {
        $absoluteOffset = abs($offsetInSeconds);
        $seconds = $absoluteOffset % 60;

        return sprintf(
            '%s%02d%02d',
            $offsetInSeconds < 0 ? '-' : '+',
            intdiv($absoluteOffset, 3600),
            intdiv($absoluteOffset % 3600, 60),
        ).($seconds !== 0 ? sprintf('%02d', $seconds) : '');
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

        // A reminder with no TIME is a relative one, which is the point of the default: fifteen
        // minutes before the event, wherever the event ends up. A TIME of the wrong type does not
        // reach this, since the constructor rejects it.
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
