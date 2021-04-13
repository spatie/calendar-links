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

        $dateTimeFormat = $link->allDay ? $this->dateFormat : $this->dateTimeFormat;

        if ($link->allDay) {
            $url .= '&ST='.$link->from->format($dateTimeFormat);
            $url .= '&DUR=allday';
            $url .= '&ET='.$link->to->format($dateTimeFormat);
        } else {
            $utcStartDateTime = (clone $link->from)->setTimezone(new DateTimeZone('UTC'));
            $utcEndDateTime = (clone $link->to)->setTimezone(new DateTimeZone('UTC'));
            $url .= '&ST='.$utcStartDateTime->format($dateTimeFormat);
            $url .= '&ET='.$utcEndDateTime->format($dateTimeFormat);
        }

        $url .= '&TITLE='.$this->sanitizeText($link->title);

        if ($link->description) {
            $url .= '&DESC='.$this->sanitizeText($link->description);
        }

        if ($link->address) {
            $url .= '&in_loc='.$this->sanitizeText($link->address);
        }

        return $url;
    }

    /**
     * Prepare text to use used in URL and parsed by the service.
     * @param string $text
     * @return string
     */
    private function sanitizeText(string $text): string
    {
        return rawurlencode($text);
    }
}
