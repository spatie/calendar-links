<?php

namespace Spatie\CalendarLinks\Generators;

use Spatie\CalendarLinks\Link;
use Spatie\CalendarLinks\Generator;

class IcsGenerator implements Generator
{
    public function generate(Link $link): string
    {
        $url = 'data:text/calendar;charset=utf8,';

        $url .= "BEGIN:VCALENDAR\n";
        $url .= "VERSION:2.0\n";
        $url .= "BEGIN:VEVENT\n";
        $url .= "DTSTART:".$link->from->format('Ymd\THis')."\n";
        $url .= "DTEND:".$link->to->format('Ymd\THis')."\n";
        $url .= "SUMMARY:{$link->title}\n";

        if ($link->description) {
            $url .= "DESCRIPTION:{$link->description}\n";
        }

        if ($link->address) {
            $url .= "LOCATION:".str_replace(',', '', $link->address)."\n";
        }

        $url .= "END:VEVENT\n";
        $url .= "END:VCALENDAR";

        return $url;
    }
}
