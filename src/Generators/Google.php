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

    /**
     * An instant, with the Z suffix that marks a UTC value. Used when no zone can be named alongside.
     * @see https://www.php.net/manual/en/function.date.php
     */
    private const string UTC_DATETIME_FORMAT = 'Ymd\THis\Z';

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
        $url = static::BASE_URL;

        // The branches below write the two endpoints and, where they can, name the zone those times
        // belong to. A zone name goes in unencoded: every name that gets this far is a TZDB region
        // name, whose only character outside the unreserved set is the `/`, and RFC 3986 lets that
        // one stand as itself in a query.
        // @see https://datatracker.ietf.org/doc/html/rfc3986#section-3.4
        if ($link->allDay) {
            // An all-day event is a pair of calendar dates rather than instants, so there is no clock
            // time to move between zones and the dates are written as they were given either way.
            $url .= '&dates='.$link->from->format(self::DATE_FORMAT).'/'.$link->to->format(self::DATE_FORMAT);

            if ($link->hasResolvableTimezones()) {
                $url .= '&ctz='.$link->fromTimezone->getName();
            }
        } elseif (! $link->hasResolvableTimezones()) {
            // Google silently ignores a ctz, stz or etz it cannot resolve, which leaves the local
            // times in `dates` to be read in whichever zone the viewer sits in, so the event lands at
            // the wrong instant for everyone else. UTC instants leave nothing to be interpreted.
            $url .= '&dates='.gmdate(self::UTC_DATETIME_FORMAT, $link->from->getTimestamp()).'/'.gmdate(self::UTC_DATETIME_FORMAT, $link->to->getTimestamp());
        } elseif ($link->hasDistinctTimezones()) {
            // Each endpoint is written as a local time in the zone that names it.
            $url .= '&dates='.$link->from->format(self::DATETIME_FORMAT).'/'.$link->to->setTimezone($link->toTimezone)->format(self::DATETIME_FORMAT);

            // stz takes priority over ctz, so ctz is not emitted alongside the pair.
            $url .= '&stz='.$link->fromTimezone->getName();
            $url .= '&etz='.$link->toTimezone->getName();
        } else {
            $url .= '&dates='.$link->from->format(self::DATETIME_FORMAT).'/'.$link->to->format(self::DATETIME_FORMAT);
            $url .= '&ctz='.$link->fromTimezone->getName();
        }

        $url .= '&text='.urlencode($link->title);

        if ($link->description !== '') {
            $url .= '&details='.urlencode($link->description);
        }

        if ($link->address !== '') {
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
                $url .= '&'.urlencode($key).(in_array($singleValue, [null, '', false], true) ? '' : '='.urlencode((string) $singleValue));
            }
        }

        return $url;
    }
}
