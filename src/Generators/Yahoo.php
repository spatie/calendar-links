<?php

namespace Spatie\CalendarLinks\Generators;

use DateTimeZone;
use Spatie\CalendarLinks\Link;
use Spatie\CalendarLinks\Generator;

/**
 * @see http://chris.photobooks.com/tests/calendar/Notes.html
 */
class Yahoo implements Generator
{
    public function generate(Link $link): string
    {
        $url = 'https://calendar.yahoo.com/?v=60&view=d&type=20';

        $url .= '&title='.urlencode($link->title);

        if ($link->allDay) {
            $dateTimeFormat = 'Ymd';
            $url .= '&st='.$link->from->format($dateTimeFormat);
            $url .= '&dur=allday';
        } else {
            $utcStartDateTime = (clone $link->from)->setTimezone(new DateTimeZone('UTC'));
            $utcEndDateTime = (clone $link->to)->setTimezone(new DateTimeZone('UTC'));
            $dateTimeFormat = $link->allDay ? 'Ymd' : 'Ymd\THis';
            $url .= '&st='.$utcStartDateTime->format($dateTimeFormat).'Z';
            $url .= '&et='.$utcEndDateTime->format($dateTimeFormat).'Z';
        }

        if ($link->description) {
            $url .= '&desc='.urlencode($link->description);
        }

        if ($link->address) {
            $url .= '&in_loc='.urlencode($link->address);
        }

        return $url;
    }
}
