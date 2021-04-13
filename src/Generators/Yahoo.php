<?php

namespace Spatie\CalendarLinks\Generators;

use DateTimeZone;
use Spatie\CalendarLinks\Generator;
use Spatie\CalendarLinks\Link;

/**
 * @see https://github.com/InteractionDesignFoundation/add-event-to-calendar-docs/blob/master/services/yahoo.md
 */
class Yahoo implements Generator
{
    /** @var string {@see https://www.php.net/manual/en/function.date.php} */
    protected $dateFormat = 'Ymd';
    protected $dateTimeFormat = 'Ymd\THis\Z';

    /** {@inheritDoc} */
    public function generate(Link $link): string
    {
        $url = 'https://calendar.yahoo.com/?v=60&view=d&type=20';
        $dateTimeFormat = $this->dateTimeFormat;
        $utcStartDateTime = (clone $link->from)->setTimezone(new DateTimeZone('UTC'));
        $utcEndDateTime = (clone $link->to)->setTimezone(new DateTimeZone('UTC'));
        $url .= '&ST='.$utcStartDateTime->format($dateTimeFormat);
        $url .= '&ET='.$utcEndDateTime->format($dateTimeFormat);

        $url .= '&TITLE='.rawurlencode($link->title);

        if ($link->description) {
            $url .= '&DESC='.rawurlencode($link->description);
        }

        if ($link->address) {
            $url .= '&in_loc='.rawurlencode($link->address);
        }

        return $url;
    }
}
