<?php

namespace Spatie\CalendarLinks\Generators;

use Spatie\CalendarLinks\Link;
use Spatie\CalendarLinks\Generator;

class Ics implements Generator
{
    public function generate(Link $link): string
    {
        $url = ['data:text/calendar;charset=utf8,',
      'BEGIN:VCALENDAR',
      'VERSION:2.0',
      'BEGIN:VEVENT',
      'DTSTART:'.$link->from->format('Ymd\THis'),
      'DTEND:'.$link->to->format('Ymd\THis'),
      'SUMMARY:'.$link->title, ];

        if ($link->description) {
            $url[] = 'DESCRIPTION:'.$link->description;
        }
        if ($link->address) {
            $url[] = 'LOCATION:'.str_replace(',', '', $link->address);
        }

        $url[] = 'END:VEVENT';
        $url[] = 'END:VCALENDAR';
        $redirectLink = implode("\n", $url);

        return $redirectLink;
    }
}
