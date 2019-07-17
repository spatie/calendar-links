<?php

namespace Spatie\CalendarLinks\Generators;

use Spatie\CalendarLinks\Link;
use Spatie\CalendarLinks\Generator;

/**
 * @see https://github.com/InteractionDesignFoundation/add-event-to-calendar-docs/blob/master/services/google.md
 */
class Google implements Generator
{
    /** {@inheritdoc} */
    public function generate(Link $link): string
    {
        $url = 'https://calendar.google.com/calendar/render?action=TEMPLATE';

        $dateTimeFormat = $link->allDay ? 'Ymd' : "Ymd\THis";
        $url .= '&text='.urlencode($link->title);
        $url .= '&dates='.$link->from->format($dateTimeFormat).'/'.$link->to->format($dateTimeFormat);
        $url .= '&ctz='.$link->from->getTimezone()->getName();

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
