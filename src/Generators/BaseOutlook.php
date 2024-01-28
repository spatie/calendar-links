<?php declare(strict_types=1);

namespace Spatie\CalendarLinks\Generators;

use DateTimeZone;
use Spatie\CalendarLinks\Generator;
use Spatie\CalendarLinks\Link;

/**
 * @see https://github.com/InteractionDesignFoundation/add-event-to-calendar-docs/blob/master/services/outlook-web.md
 */
abstract class BaseOutlook implements Generator
{
    /** @see https://www.php.net/manual/en/function.date.php */
    private const DATE_FORMAT = 'Y-m-d';

    /** @see https://www.php.net/manual/en/function.date.php */
    private const DATETIME_FORMAT = 'Y-m-d\TH:i:s\Z';

    /**
     * Get base URL for links.
     * @return non-empty-string
     */
    abstract protected function baseUrl(): string;

    /** @inheritDoc */
    public function generate(Link $link): string
    {
        $url = $this->baseUrl();

        $dateTimeFormat = $link->allDay ? self::DATE_FORMAT : self::DATETIME_FORMAT;

        $utcStartDateTime = $link->from->setTimezone(new DateTimeZone('UTC'));
        $utcEndDateTime = $link->to->setTimezone(new DateTimeZone('UTC'));

        $url .= '&startdt='.$utcStartDateTime->format($dateTimeFormat);
        $url .= '&enddt='.$utcEndDateTime->format($dateTimeFormat);

        if ($link->allDay) {
            $url .= '&allday=true';
        }

        $url .= '&subject='.$this->sanitizeString($link->title);

        if ($link->description) {
            $url .= '&body='.$this->sanitizeString($link->description);
        }

        if ($link->address) {
            $url .= '&location='.$this->sanitizeString($link->address);
        }

        return $url;
    }

    private function sanitizeString(string $input): string
    {
        return rawurlencode($input);
    }
}
