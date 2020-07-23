<?php

namespace Spatie\CalendarLinks\Generators;

use Spatie\CalendarLinks\Generator;
use Spatie\CalendarLinks\Link;

/**
 * @see https://icalendar.org/RFC-Specifications/iCalendar-RFC-5545/
 */
class Ics implements Generator
{
    /** @var string {@see https://www.php.net/manual/en/function.date.php} */
    protected $dateFormat = 'Ymd';
    protected $dateTimeFormat = 'e:Ymd\THis';

    /** @var array */
    protected $options = [];

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    /** {@inheritdoc} */
    public function generate(Link $link): string
    {
        $url = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'BEGIN:VEVENT',
            'UID:'.($this->options['UID'] ?? $this->generateEventUid($link)),
            'SUMMARY:'.$this->escapeString($link->title),
        ];

        $dateTimeFormat = $link->allDay ? $this->dateFormat : $this->dateTimeFormat;

        if ($link->allDay) {
            $url[] = 'DTSTART:'.$link->from->format($dateTimeFormat);
            $url[] = 'DURATION:P'.($link->from->diffInDays($link->to) + 1).'D';
        } else {
            $url[] = 'DTSTART;TZID='.$link->from->format($dateTimeFormat);
            $url[] = 'DTEND;TZID='.$link->to->format($dateTimeFormat);
        }

        if ($link->description) {
            $url[] = 'DESCRIPTION:'.$this->escapeString($link->description);
        }
        if ($link->address) {
            $url[] = 'LOCATION:'.$this->escapeString($link->address);
        }

        $url[] = 'END:VEVENT';
        $url[] = 'END:VCALENDAR';
        $redirectLink = implode("\r\n", $url);

        return 'data:text/calendar;charset=utf8;base64,'.base64_encode($redirectLink);
    }

    /** @see https://tools.ietf.org/html/rfc5545.html#section-3.3.11 */
    protected function escapeString(string $field): string
    {
        return addcslashes($field, "\r\n,;");
    }

    /** @see https://tools.ietf.org/html/rfc5545#section-3.8.4.7 */
    protected function generateEventUid(Link $link): string
    {
        return md5($link->from->format(\DateTimeInterface::ATOM).$link->to->format(\DateTimeInterface::ATOM).$link->title.$link->address);
    }
}
