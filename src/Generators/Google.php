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
    /** @var string {@see https://www.php.net/manual/en/function.date.php} */
    protected $dateFormat = 'Ymd';
    protected $dateTimeFormat = 'Ymd\THis\Z';

    /** {@inheritDoc} */
    public function generate(Link $link): string
    {
        $url = 'https://calendar.google.com/calendar/render?action=TEMPLATE';

        $utcStartDateTime = (clone $link->from)->setTimezone(new DateTimeZone('UTC'));
        $utcEndDateTime = (clone $link->to)->setTimezone(new DateTimeZone('UTC'));
        $dateTimeFormat = $link->allDay ? $this->dateFormat : $this->dateTimeFormat;
        $url .= '&dates='.$utcStartDateTime->format($dateTimeFormat).'/'.$utcEndDateTime->format($dateTimeFormat);

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
