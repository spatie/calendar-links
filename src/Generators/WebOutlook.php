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
    /** @var string {@see https://www.php.net/manual/en/function.date.php} */
    const DATE_FORMAT = 'Y-m-d';
    const DATETIME_FORMAT = 'Y-m-d\TH:i:s\Z';

    /** @inheritDoc */
    public function generate(Link $link): string
    {
        $url = 'https://outlook.live.com/calendar/deeplink/compose?path=/calendar/action/compose&rru=addevent';

        $dateTimeFormat = $link->allDay ? self::DATE_FORMAT : self::DATETIME_FORMAT;

        $utcStartDateTime = (clone $link->from)->setTimezone(new DateTimeZone('UTC'));
        $utcEndDateTime = (clone $link->to)->setTimezone(new DateTimeZone('UTC'));

        $url .= '&startdt='.$utcStartDateTime->format($dateTimeFormat);
        $url .= '&enddt='.$utcEndDateTime->format($dateTimeFormat);

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
