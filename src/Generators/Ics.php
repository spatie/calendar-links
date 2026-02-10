<?php

declare(strict_types=1);

namespace Spatie\CalendarLinks\Generators;

use Spatie\CalendarLinks\Generator;
use Spatie\CalendarLinks\Link;

/**
 * @api
 * @see https://icalendar.org/RFC-Specifications/iCalendar-RFC-5545/
 * @psalm-type IcsOptions = array{UID?: string, URL?: string, PRODID?: string, REMINDER?: array{DESCRIPTION?: string, TIME?: \DateTimeInterface}}
 * @psalm-type IcsPresentationOptions = array{format?: self::FORMAT_*}
 */
class Ics implements Generator
{
    public const string FORMAT_HTML = 'html';
    public const string FORMAT_FILE = 'file';

    /** @see https://www.php.net/manual/en/function.date.php */
    protected string $dateFormat = 'Ymd';

    protected string $dateTimeFormat = 'Ymd\THis\Z';

    /** @psalm-var IcsOptions */
    protected array $options = [];

    /** @psalm-var IcsPresentationOptions */
    protected $presentationOptions = [];

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
            $url[] = 'DTSTART:'.gmdate($dateTimeFormat, $link->from->getTimestamp());
            $url[] = 'DTEND:'.gmdate($dateTimeFormat, $link->to->getTimestamp());
        }

        if ($link->description) {
            $url[] = 'DESCRIPTION:'.$this->escapeString(strip_tags($link->description));
        }
        if ($link->address) {
            $url[] = 'LOCATION:'.$this->escapeString($link->address);
        }

        if (isset($this->options['URL'])) {
            $url[] = 'URL;VALUE=URI:'.$this->options['URL'];
        }

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
     * @param \Spatie\CalendarLinks\Link $link
     * @return list<string>
     */
    private function generateAlertComponent(Link $link): array
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
