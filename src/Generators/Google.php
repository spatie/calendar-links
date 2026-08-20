<?php

declare(strict_types=1);

namespace Spatie\CalendarLinks\Generators;

use Spatie\CalendarLinks\Generator;
use Spatie\CalendarLinks\Link;

/**
 * @api
 * @see https://github.com/InteractionDesignFoundation/add-event-to-calendar-docs/blob/main/services/google.md
 * @psalm-type GoogleUrlParameters = array<string, scalar|null|list<scalar|null>>
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

        // Each endpoint is written as a local time in the zone that names it below.
        $to = $link->hasDistinctTimezones() ? $link->to->setTimezone($link->toTimezone) : $link->to;
        $url .= '&dates='.$link->from->format($dateTimeFormat).'/'.$to->format($dateTimeFormat);

        // Not URL-encoded intentionally: Google Calendar handles unencoded timezone names (e.g. Etc/GMT+5) correctly.
        if ($link->hasDistinctTimezones()) {
            // stz takes priority over ctz, so ctz is not emitted alongside the pair.
            $url .= '&stz='.$link->fromTimezone->getName();
            $url .= '&etz='.$link->toTimezone->getName();
        } else {
            $url .= '&ctz='.$link->from->getTimezone()->getName();
        }
        $url .= '&text='.urlencode($link->title);

        if ($link->description) {
            $url .= '&details='.urlencode($link->description);
        }

        if ($link->address) {
            $url .= '&location='.urlencode($link->address);
        }

        if ($link->guests !== []) {
            $guestList = [];
            foreach ($link->guests as $guest) {
                // Google marks an optional attendee with an `_o` suffix on the address.
                $guestList[] = $guest['email'].($guest['optional'] ? '_o' : '');
            }

            // Google decodes the query data before splitting it, so encoding the commas is harmless here.
            $url .= '&add='.urlencode(implode(',', $guestList));
        }

        foreach ($this->urlParameters as $key => $value) {
            // A list of values is flattened into a repeated parameter (e.g. Google's sprop).
            foreach (is_array($value) ? $value : [$value] as $singleValue) {
                $url .= '&'.urlencode($key).(in_array($singleValue, [null, ''], true) ? '' : '='.urlencode((string) $singleValue));
            }
        }

        return $url;
    }
}
