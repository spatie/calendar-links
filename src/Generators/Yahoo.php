<?php

namespace Spatie\CalendarLinks\Generators;

use DateTime;
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
        $dateTimeFormat = $link->allDay ? 'Ymd' : DateTime::ATOM;
        $url .= '&st='.$link->from->format($dateTimeFormat);
        $url .= '&et='.$link->to->format($dateTimeFormat);

        if ($link->description) {
            $url .= '&desc='.urlencode($link->description);
        }

        if ($link->address) {
            $url .= '&in_loc='.urlencode($link->address);
        }

        return $url;
    }
}
