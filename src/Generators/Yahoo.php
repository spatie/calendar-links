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

    /** {@inheritdoc} */
    public function generate(Link $link): string
    {
        $url = 'https://calendar.yahoo.com/?v=60&view=d&type=20';

        $dateTimeFormat = $link->allDay ? $this->dateFormat : $this->dateTimeFormat;

        if ($link->allDay && $link->from->diff($link->to)->days === 1) {
            $url .= '&st='.$link->from->format($dateTimeFormat);
            $url .= '&dur=allday';
        } else {
            $utcStartDateTime = (clone $link->from)->setTimezone(new DateTimeZone('UTC'));
            $utcEndDateTime = (clone $link->to)->setTimezone(new DateTimeZone('UTC'));

            $url .= '&st='.$utcStartDateTime->format($dateTimeFormat);

            /**
             * Yahoo has a bug on parsing end date parameter: it ignores timezone, assuming
             * that it's specified in user's tz. In order to bypass it, we can use duration ("dur")
             * parameter instead of "et", but this parameter has a limitation cause by it's format HHmm:
             * the max duration is 99hours and 59 minutes (dur=9959).
             */
            $maxDurationInSecs = (59 * 60 * 60) + (59 * 60);
            $canUseDuration = $maxDurationInSecs > ($utcEndDateTime->getTimestamp() - $utcStartDateTime->getTimestamp());
            if ($canUseDuration) {
                $dateDiff = $utcStartDateTime->diff($utcEndDateTime);
                $url .= '&dur='.$dateDiff->format('%H%I');
            } else {
                $url .= '&et='.$utcEndDateTime->format($dateTimeFormat);
            }
        }

        $url .= '&title='.urlencode($link->title);

        if ($link->description) {
            $url .= '&desc='.urlencode($link->description);
        }

        if ($link->address) {
            $url .= '&in_loc='.urlencode($link->address);
        }

        return $url;
    }
}
