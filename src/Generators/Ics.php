<?php

declare(strict_types=1);

namespace Spatie\CalendarLinks\Generators;

use Spatie\CalendarLinks\Generator;
use Spatie\CalendarLinks\Link;

/**
 * @api
 * @see https://icalendar.org/RFC-Specifications/iCalendar-RFC-5545/
 * @psalm-type IcsOptions = array{UID?: string, URL?: string, PRODID?: string, REMINDER?: array{DESCRIPTION?: string, TIME?: \DateTimeInterface}, TRANSP?: 'OPAQUE'|'TRANSPARENT', CLASS?: 'PUBLIC'|'PRIVATE'|'CONFIDENTIAL', RRULE?: string, X-MICROSOFT-CDO-BUSYSTATUS?: 'FREE'|'TENTATIVE'|'BUSY'|'OOF'}
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

    /** @psalm-var IcsOptions */
    protected array $options = [];

    /** @psalm-var IcsPresentationOptions */
    protected array $presentationOptions = [];

    /**
     * @param IcsOptions $options Optional ICS properties and components
     * @param IcsPresentationOptions $presentationOptions
     */
    public function __construct(array $options = [], array $presentationOptions = [])
    {
        $this->options = $options;
        $this->presentationOptions = $presentationOptions;
    }

    /** @inheritDoc */
    #[\Override]
    public function generate(Link $link): string
    {
        $url = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0', // @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.7.4
            'PRODID:'.($this->options['PRODID'] ?? 'Spatie calendar-links'), // @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.7.3
            ...$this->additionalCalendarProperties($link),
            'BEGIN:VEVENT',
            'UID:'.($this->options['UID'] ?? $this->generateEventUid($link)),
            'SUMMARY:'.$this->escapeString($link->title),
        ];

        $dateTimeFormat = $link->allDay ? $this->dateFormat : $this->dateTimeFormat;

        // DTSTAMP must always be UTC datetime. @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.8.7.2
        if ($link->allDay) {
            $url[] = 'DTSTAMP:'.gmdate($this->dateTimeFormat, $link->from->getTimestamp());
            $url[] = 'DTSTART;VALUE=DATE:'.$link->from->format($dateTimeFormat);
            $url[] = 'DURATION:P'.(max(1, (int) $link->from->diff($link->to)->days)).'D';
        } else {
            $url[] = 'DTSTAMP:'.gmdate($dateTimeFormat, $link->from->getTimestamp());

            if ($link->hasDistinctTimezones()) {
                // Both endpoints are written as local times, each named by its own TZID, so the file
                // shows the event's own zones rather than flattening them to UTC.
                // @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.2.19
                $url[] = 'DTSTART;TZID='.$link->fromTimezone->getName().':'.$link->from->format(self::LOCAL_DATETIME_FORMAT);
                $url[] = 'DTEND;TZID='.$link->toTimezone->getName().':'.$link->to->setTimezone($link->toTimezone)->format(self::LOCAL_DATETIME_FORMAT);
            } else {
                $url[] = 'DTSTART:'.gmdate($dateTimeFormat, $link->from->getTimestamp());
                $url[] = 'DTEND:'.gmdate($dateTimeFormat, $link->to->getTimestamp());
            }
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
        // so they are emitted verbatim as well.
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

        if (is_array($this->options['REMINDER'] ?? null)) {
            $url = [...$url, ...$this->generateAlertComponent($link)];
        }

        $url = [...$url, ...$this->additionalEventProperties($link)];

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
        return 'data:text/calendar;charset=utf8;base64,'.base64_encode(implode("\r\n", $propertiesAndComponents));
    }

    /**
     * @param non-empty-list<string> $propertiesAndComponents
     * @return non-empty-string
     */
    protected function buildFile(array $propertiesAndComponents): string
    {
        return implode("\r\n", $propertiesAndComponents);
    }

    /** @see https://tools.ietf.org/html/rfc5545.html#section-3.3.11 */
    protected function escapeString(string $field): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\r", "\n"],
            ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'],
            $field
        );
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
     * Extension point: extra properties for the VEVENT component, emitted before END:VEVENT
     * (e.g. CATEGORIES, STATUS, ORGANIZER or any X- property). One full content line per entry,
     * already escaped: TEXT values should go through escapeString().
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
        $description = $this->options['REMINDER']['DESCRIPTION'] ?? null;
        if (! is_string($description)) {
            $description = 'Reminder: '.$this->escapeString($link->title);
        }

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
