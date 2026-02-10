<?php

declare(strict_types=1);

namespace Spatie\CalendarLinks\Generators;

use Spatie\CalendarLinks\Generator;
use Spatie\CalendarLinks\Link;

/**
 * @api
 * @see https://github.com/InteractionDesignFoundation/add-event-to-calendar-docs/blob/main/services/google.md
 * @psalm-type GoogleUrlParameters = array<string, scalar|null>
 */
class Google implements Generator
{
    /** @see https://www.php.net/manual/en/function.date.php */
    private const string DATE_FORMAT = 'Ymd';

    /** @see https://www.php.net/manual/en/function.date.php */
    private const string DATETIME_FORMAT = 'Ymd\THis';

    /** @psalm-var GoogleUrlParameters */
    protected array $urlParameters = [];

    /** @psalm-param GoogleUrlParameters $urlParameters */
    public function __construct(array $urlParameters = [])
    {
        $this->urlParameters = $urlParameters;
    }

    /** @var non-empty-string */
    protected const string BASE_URL = 'https://calendar.google.com/calendar/render?action=TEMPLATE';

    /** @inheritDoc */
    #[\Override]
    public function generate(Link $link): string
    {
        $url = self::BASE_URL;

        $dateTimeFormat = $link->allDay ? self::DATE_FORMAT : self::DATETIME_FORMAT;
        $url .= '&dates='.$link->from->format($dateTimeFormat).'/'.$link->to->format($dateTimeFormat);
        // Not URL-encoded intentionally: Google Calendar handles unencoded timezone names (e.g. Etc/GMT+5) correctly.
        $url .= '&ctz=' . $link->from->getTimezone()->getName();
        $url .= '&text='.urlencode($link->title);

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
