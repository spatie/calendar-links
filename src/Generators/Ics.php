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
      'DTSTART:'.$link->from,
      'DTEND:'.$link->to,
      'SUMMARY:'.$link->title, ];

        if ($link->description) {
            $url[] = 'DESCRIPTION:'.$this->escapeString($link->description);
        }
        if ($link->address) {
            $url[] = 'LOCATION:'.$this->escapeString($link->address);
        }

        $url[] = 'END:VEVENT';
        $url[] = 'END:VCALENDAR';
        $redirectLink = implode('%0A', $url);

        return $redirectLink;
    }

    protected function escapeString(string $field): string
    {
        return addcslashes($field, "\n,");
    }
}
