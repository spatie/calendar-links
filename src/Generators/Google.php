<?php

declare(strict_types=1);

namespace Spatie\CalendarLinks\Generators;

use Spatie\CalendarLinks\Generator;
use Spatie\CalendarLinks\Link;

/**
 * @see https://github.com/InteractionDesignFoundation/add-event-to-calendar-docs/blob/main/services/google.md
 * @psalm-type GoogleUrlParameters = array<string, scalar|null>
 */
class Google implements Generator
{
    /** @see https://www.php.net/manual/en/function.date.php */
    private const DATE_FORMAT = 'Ymd';

    /** @see https://www.php.net/manual/en/function.date.php */
    private const DATETIME_FORMAT = 'Ymd\THis';

    /** @psalm-var GoogleUrlParameters */
    protected array $urlParameters = [];

    /** @psalm-param GoogleUrlParameters $urlParameters */
    public function __construct(array $urlParameters = [])
    {
        $this->urlParameters = $urlParameters;
    }

    /** @var non-empty-string */
    protected const BASE_URL = 'https://calendar.google.com/calendar/render?action=TEMPLATE';

    /** @inheritDoc */
    public function generate(Link $link): string
    {
        $url = self::BASE_URL;

        $dateTimeFormat = $link->allDay ? self::DATE_FORMAT : self::DATETIME_FORMAT;
        $url .= '&dates='.$link->from->format($dateTimeFormat).'/'.$link->to->format($dateTimeFormat);
        $url .= '&text='.urlencode($link->title);
        
        if (!$link->allDay) {
            $url .= '&ctz=' . $link->from->getTimezone()->getName();
        }

        if ($link->description) {
            $url .= '&details='.urlencode($link->description);
        }

        if ($link->address) {
            $url .= '&location='.urlencode($link->address);
        }

        foreach ($this->urlParameters as $key => $value) {
            $url .= '&'.urlencode($key).(in_array($value, [null, ''], true) ? '' : '='.urlencode((string) $value));
        }

        return $url;
    }
}
