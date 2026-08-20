<?php

declare(strict_types=1);

namespace Spatie\CalendarLinks\Generators;

use DateTimeZone;
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
    private const string DATETIME_FORMAT = 'Ymd\THis\Z';

    /** @var non-empty-string */
    protected const string BASE_URL = 'https://calendar.yahoo.com/?v=60&view=d&type=20';

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

        if ($link->guests !== []) {
            $guestList = [];
            foreach ($link->guests as $guest) {
                // Yahoo splits `inv_list` before decoding it, so every address is encoded on its own
                // and the parts are joined with a literal comma.
                $guestList[] = $this->sanitizeText($guest['email']);
            }

            // Yahoo has no optional role, so optional guests are invited as required ones.
            $url .= '&inv_list='.implode(',', $guestList);
        }

        foreach ($this->urlParameters as $key => $value) {
            // A list of values is flattened into a repeated parameter.
            foreach (is_array($value) ? $value : [$value] as $singleValue) {
                $url .= '&'.urlencode($key).(in_array($singleValue, [null, ''], true) ? '' : '='.$this->sanitizeText((string) $singleValue));
            }
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
