<?php

namespace Spatie\CalendarLinks\Generators;

use Spatie\CalendarLinks\Generator;
use Spatie\CalendarLinks\Link;

/**
 * @see https://icalendar.org/RFC-Specifications/iCalendar-RFC-5545/
 */
class Ics implements Generator
{
    public const FORMAT_HTML = 'html';
    public const FORMAT_FILE = 'file';

    /** @var string {@see https://www.php.net/manual/en/function.date.php} */
    protected $dateFormat = 'Ymd';
    /** @var string */
    protected $dateTimeFormat = 'Ymd\THis\Z';

    /** @var array<non-empty-string, non-empty-string> */
    protected $options = [];

    /** @var array{format?: self::FORMAT_*} */
    protected $presentationOptions = [];

    /**
     * @param array<non-empty-string, non-empty-string> $options Optional ICS properties and components
     * @param array{format?: self::FORMAT_*} $presentationOptions
     */
    public function __construct(array $options = [], array $presentationOptions = [])
    {
        $this->options = $options;
        $this->presentationOptions = $presentationOptions;
    }

    /** {@inheritDoc} */
    public function generate(Link $link): string
    {
        $url = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0', // @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.7.4
            'PRODID:Spatie calendar-links', // @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.7.3
            'BEGIN:VEVENT',
            'UID:'.($this->options['UID'] ?? $this->generateEventUid($link)),
            'SUMMARY:'.$this->escapeString($link->title),
        ];

        $dateTimeFormat = $link->allDay ? $this->dateFormat : $this->dateTimeFormat;

        if ($link->allDay) {
            $url[] = 'DTSTAMP:'.$link->from->format($dateTimeFormat);
            $url[] = 'DTSTART:'.$link->from->format($dateTimeFormat);
            $url[] = 'DURATION:P'.(max(1, $link->from->diff($link->to)->days)).'D';
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

        if (isset($this->options['REMINDER'])) {
            $description = 'Reminder: '.$this->escapeString($link->title);
            if (isset($this->options['REMINDER']['DESCRIPTION'])) {
                $description = $this->escapeString($this->options['REMINDER']['DESCRIPTION']);
            }

            $trigger = '-PT15M';
            if (isset($this->options[ 'REMINDER'][ 'TIME'])) {
                $trigger = 'VALUE=DATE-TIME:'.gmdate($dateTimeFormat, $this->options[ 'REMINDER'][ 'TIME']->getTimestamp());
            }

            $url[] = 'BEGIN:VALARM';
            $url[] = 'ACTION:DISPLAY';
            $url[] = 'DESCRIPTION:'.$description;
            $url[] = 'TRIGGER:'.$trigger;
            $url[] = 'END:VALARM';
        }

        $url[] = 'END:VEVENT';
        $url[] = 'END:VCALENDAR';

        $format = $this->presentationOptions['format'] ?? self::FORMAT_HTML;

        return match ($format) {
            'file' => $this->buildFile($url),
            default => $this->buildLink($url),
        };
    }

    protected function buildLink(array $propertiesAndComponents): string
    {
        return 'data:text/calendar;charset=utf8;base64,'.base64_encode(implode("\r\n", $propertiesAndComponents));
    }

    protected function buildFile(array $propertiesAndComponents): string
    {
        return implode("\r\n", $propertiesAndComponents);
    }

    /** @see https://tools.ietf.org/html/rfc5545.html#section-3.3.11 */
    protected function escapeString(string $field): string
    {
        return addcslashes($field, "\r\n,;");
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
}
