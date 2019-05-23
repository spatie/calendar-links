<?php

namespace Spatie\CalendarLinks\Generators;

use DateTimeZone;
use Spatie\CalendarLinks\Link;
use Spatie\CalendarLinks\Generator;

/**
 * @see https://github.com/InteractionDesignFoundation/add-event-to-calendar-docs/blob/master/services/outlook-live.md
 */
class WebOutlook implements Generator
{
    /** {@inheritdoc} */
    public function generate(Link $link): string
    {
        $url = 'https://outlook.live.com/owa/?path=/calendar/action/compose&rru=addevent';

        $dateTimeFormat = $link->allDay ? 'Ymd' : "Ymd\THis";
        $utcStartDateTime = (clone $link->from)->setTimezone(new DateTimeZone('UTC'));
        $utcEndDateTime = (clone $link->to)->setTimezone(new DateTimeZone('UTC'));
        $url .= '&startdt='.$utcStartDateTime->format($dateTimeFormat);

        $isSingleDayEvent = $link->to->diff($link->from)->d < 2;
        $canOmitEndDateTime = $link->allDay && $isSingleDayEvent;
        if (! $canOmitEndDateTime) {
            $url .= '&enddt='.$utcEndDateTime->format($dateTimeFormat);
        }

        if ($link->allDay) {
            $url .= '&allday=true';
        }

        $url .= '&subject='.urlencode($link->title);

        if ($link->description) {
            $url .= '&body='.urlencode($link->description);
        }

        if ($link->address) {
            $url .= '&location='.urlencode($link->address);
        }

        return $url;
    }
}
