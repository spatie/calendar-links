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
      'PRODID:-//Spatie//CalendarLinks//EN',
      'VERSION:2.0',
      'BEGIN:VEVENT',
      'UID:'. $link->from . $link->to,
      'DTSTAMP:'. (new \DateTime())->format('Ymd\THis'),
      'DTSTART:'.$link->from,
      'DTEND:'.$link->to,
      'SUMMARY:'.$link->title, ];

        if ($link->description) {
            $url[] = 'DESCRIPTION:'.addcslashes($link->description, "\n");
        }
        if ($link->address) {
            $url[] = 'LOCATION:'.str_replace(',', '', $link->address);
        }

        $url[] = 'END:VEVENT';
        $url[] = 'END:VCALENDAR';
        $redirectLink = implode('%0D%0A', $url);

        return $redirectLink;
    }
}
