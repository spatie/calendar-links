<?php

namespace Spatie\CalendarLinks\Generators;

use Spatie\CalendarLinks\Link;
use Spatie\CalendarLinks\Generator;

class Google implements Generator
{
    public function generate(Link $link)
    {
        $url = 'https://calendar.google.com/calendar/render?action=TEMPLATE';

        $url .= '&text='.urlencode($link->title);
        $url .= '&dates='.$link->from.'/'.$link->to;

        if ($link->description) {
            $url .= '&details='.urlencode($link->description);
        }

        if ($link->address) {
            $url .= '&location='.urlencode($link->address);
        }

        $url .= '&sprop=&sprop=name:';

        return $url;
    }
}
