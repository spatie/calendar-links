<?php

namespace Spatie\CalendarLinks\Generators;

use DateTimeZone;
use Spatie\CalendarLinks\Generator;
use Spatie\CalendarLinks\Link;

/**
 * @see https://github.com/InteractionDesignFoundation/add-event-to-calendar-docs/blob/master/services/google.md
 */
class Google implements Generator
{
    /** @see https://www.php.net/manual/en/function.date.php */
    private const DATE_FORMAT = 'Ymd';

    /** @see https://www.php.net/manual/en/function.date.php */
    private const DATETIME_FORMAT = 'Ymd\THis\Z';

    /** @var non-empty-string */
    protected const BASE_URL = 'https://calendar.google.com/calendar/render?action=TEMPLATE';

    /** @inheritDoc */
    public function generate(Link $link): string
    {
        $url = self::BASE_URL;

        $utcStartDateTime = $link->from->setTimezone(new DateTimeZone('UTC'));
        $utcEndDateTime = $link->to->setTimezone(new DateTimeZone('UTC'));
        $dateTimeFormat = $link->allDay ? self::DATE_FORMAT : self::DATETIME_FORMAT;
        $url .= '&dates='.$utcStartDateTime->format($dateTimeFormat).'/'.$utcEndDateTime->format($dateTimeFormat);

        // Add timezone name if it is specified in both from and to dates and is the same for both
        if ($link->from->getTimezone()->getName() === $link->to->getTimezone()->getName()) {
            $url .= '&ctz=' . $link->from->getTimezone()->getName();
        }

        $url .= '&text='.urlencode($link->title);

        if ($link->description) {
            $url .= '&details='.urlencode($link->description);
        }

        if ($link->address) {
            $url .= '&location='.urlencode($link->address);
        }

        return $url;
    }
}
