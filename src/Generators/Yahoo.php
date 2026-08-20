<?php

declare(strict_types=1);

namespace Spatie\CalendarLinks\Generators;

use Spatie\CalendarLinks\Generator;
use Spatie\CalendarLinks\Link;

/**
 * @api
 * @see https://github.com/InteractionDesignFoundation/add-event-to-calendar-docs/blob/main/services/yahoo.md
 * @psalm-type YahooUrlParameters = array<string, scalar|null|list<scalar|null>>
 */
class Yahoo implements Generator
{
    /** @see https://www.php.net/manual/en/function.date.php */
    private const string DATE_FORMAT = 'Ymd';

    /** @see https://www.php.net/manual/en/function.date.php */
    private const string DATETIME_FORMAT = 'Ymd\THis';

    /** Parameter holding a comma separated list of email addresses, which Yahoo splits before decoding. */
    private const string ADDRESS_LIST_PARAMETER = 'inv_list';

    /** @var non-empty-string */
    protected const string BASE_URL = 'https://calendar.yahoo.com/?v=60';

    /** @psalm-var YahooUrlParameters */
    protected array $urlParameters = [];

    /** @psalm-param YahooUrlParameters $urlParameters */
    public function __construct(array $urlParameters = [])
    {
        $this->urlParameters = $urlParameters;
    }

    /** {@inheritDoc} */
    #[\Override]
    public function generate(Link $link): string
    {
        $url = self::BASE_URL;

        if ($link->allDay) {
            $url .= '&ST='.$link->from->format(self::DATE_FORMAT);

            // Yahoo ignores `DUR` as soon as `ET` is present, so only one of them can be sent:
            // the all-day marker for a single day, the exclusive end date for a longer event.
            $url .= $this->isSingleDayEvent($link)
                ? '&DUR=allday'
                : '&ET='.$link->to->format(self::DATE_FORMAT);
        } else {
            // Yahoo has no timezone parameter and renders `ST` and `ET` as local wall clock time,
            // so both endpoints are sent in the event timezone, without converting them to UTC.
            $url .= '&ST='.$link->from->format(self::DATETIME_FORMAT);
            $url .= '&ET='.$link->to->format(self::DATETIME_FORMAT);
        }

        $url .= '&TITLE='.$this->sanitizeText($link->title);

        if ($link->description) {
            $url .= '&DESC='.$this->sanitizeText($link->description);
        }

        if ($link->address) {
            $url .= '&in_loc='.$this->sanitizeText($link->address);
        }

        if ($link->guests !== []) {
            // Yahoo has no optional role, so optional guests are invited as required ones.
            $url .= '&'.self::ADDRESS_LIST_PARAMETER.'='.$this->sanitizeAddressList(
                implode(',', array_column($link->guests, 'email'))
            );
        }

        foreach ($this->urlParameters as $key => $value) {
            // A list of values is flattened into a repeated parameter.
            foreach (is_array($value) ? $value : [$value] as $singleValue) {
                $url .= '&'.urlencode($key).(in_array($singleValue, [null, ''], true) ? '' : '='.$this->sanitizeParameterValue($key, (string) $singleValue));
            }
        }

        return $url;
    }

    /** All-day events carry an exclusive end date, so a single day event ends on the next day. */
    private function isSingleDayEvent(Link $link): bool
    {
        return $link->from->modify('+1 day')->format(self::DATE_FORMAT) === $link->to->format(self::DATE_FORMAT);
    }

    private function sanitizeParameterValue(string $key, string $value): string
    {
        return $key === self::ADDRESS_LIST_PARAMETER
            ? $this->sanitizeAddressList($value)
            : $this->sanitizeText($value);
    }

    /**
     * Prepare a comma separated list of email addresses.
     * Yahoo splits the value before decoding it, so every address is encoded on its own
     * and the parts are joined with a literal comma.
     */
    private function sanitizeAddressList(string $addressList): string
    {
        return implode(',', array_map($this->sanitizeText(...), explode(',', $addressList)));
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
