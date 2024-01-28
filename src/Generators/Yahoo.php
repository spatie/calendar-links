<?php declare(strict_types=1);

namespace Spatie\CalendarLinks\Generators;

use DateTimeZone;
use Spatie\CalendarLinks\Generator;
use Spatie\CalendarLinks\Link;

/**
 * @see https://github.com/InteractionDesignFoundation/add-event-to-calendar-docs/blob/master/services/yahoo.md
 */
class Yahoo implements Generator
{
    /** @see https://www.php.net/manual/en/function.date.php */
    private const DATE_FORMAT = 'Ymd';

    /** @see https://www.php.net/manual/en/function.date.php */
    private const DATETIME_FORMAT = 'Ymd\THis\Z';

    /** @var non-empty-string */
    protected const BASE_URL = 'https://calendar.yahoo.com/?v=60&view=d&type=20';

    /** @inheritDoc */
    public function generate(Link $link): string
    {
        $url = self::BASE_URL;

        $dateTimeFormat = $link->allDay ? self::DATE_FORMAT : self::DATETIME_FORMAT;

        if ($link->allDay) {
            $url .= '&ST='.$link->from->format($dateTimeFormat);
            $url .= '&DUR=allday';
            $url .= '&ET='.$link->to->format($dateTimeFormat);
        } else {
            $utcStartDateTime = $link->from->setTimezone(new DateTimeZone('UTC'));
            $utcEndDateTime = $link->to->setTimezone(new DateTimeZone('UTC'));
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
