<?php

namespace Spatie\CalendarLinks\Generators;

use Spatie\CalendarLinks\Link;
use Spatie\CalendarLinks\Generator;

class YahooGenerator implements Generator
{
    public function generate(Link $link): string
    {
        $url = 'https://calendar.yahoo.com/?v=60&view=d&type=20';

        $url .= '&title='.urlencode($link->title);
        $url .= '&st='.$link->from->format('Ymd\THis');
        $url .= '&et='.$link->to->format('Ymd\THis');

        if ($link->description) {
            $url .= '&desc='.urlencode($link->description);
        }

        if ($link->address) {
            $url .= '&in_loc='.urlencode($link->address);
        }

        return $url;
    }
}
