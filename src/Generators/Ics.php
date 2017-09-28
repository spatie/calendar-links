<?php

namespace Spatie\CalendarLinks\Generators;

use Spatie\CalendarLinks\Link;
use Spatie\CalendarLinks\Generator;

class Ics implements Generator
{
    public function generate(Link $link): string
    {
        $url = 'data:text/calendar;charset=utf8,';

        $url .= 'BEGIN:VCALENDAR%0A';
        $url .= 'VERSION:2.0%0A';
        $url .= 'BEGIN:VEVENT%0A';
        $url .= 'DTSTART:'.$link->from->format('Ymd\THis').'%0A';
        $url .= 'DTEND:'.$link->to->format('Ymd\THis').'%0A';
        $url .= "SUMMARY:{$link->title}%0A";

        if ($link->description) {
            $url .= "DESCRIPTION:{$link->description}%0A";
        }

        if ($link->address) {
            $url .= 'LOCATION:'.str_replace(',', '', $link->address).'%0A';
        }

        $url .= 'END:VEVENT%0A';
        $url .= 'END:VCALENDAR';

        return $url;
    }
}
