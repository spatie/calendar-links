<?php

namespace Spatie\CalendarLinks\Generators;

use DateTimeZone;
use Spatie\CalendarLinks\Generator;
use Spatie\CalendarLinks\Link;

/**
 * @see https://answers.microsoft.com/en-us/outlook_com/forum/ocalendar-oaddevent/link-to-outlook-live-calendar-correct-url/67b96c7d-336a-4ae9-b0fe-3b35ed8e959a
 */
class OutlookCom implements Generator
{
    /** @inheritdoc */
    public function generate(Link $link): string
    {
        $url = 'https://outlook.live.com/owa/?path=/calendar/view/month&rru=addevent';

        $url .= '&subject='.urlencode($link->title);

        $utcStartDateTime = (clone $link->from)->setTimezone(new DateTimeZone('UTC'));
        $utcEndDateTime = (clone $link->to)->setTimezone(new DateTimeZone('UTC'));
        $url .= '&startdt='.$utcStartDateTime->format('Ymd\THis').'Z';
        $url .= '&enddt='.$utcEndDateTime->format('Ymd\THis').'Z';
        if ($link->allDay) {
            $url .= '&allday=true';
        }

        if ($link->description) {
            $url .= '&body='.urlencode($link->description);
        }

        if ($link->address) {
            $url .= '&location='.urlencode($link->address);
        }

        return $url;
    }
}
