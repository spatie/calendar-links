<?php declare(strict_types=1);

namespace Spatie\CalendarLinks\Generators;

class WebOffice extends BaseOutlook
{
    /** @var non-empty-string */
    protected const BASE_URL = 'https://outlook.office.com/calendar/deeplink/compose?path=/calendar/action/compose&rru=addevent';

    /** @inheritDoc */
    public function baseUrl(): string
    {
        return static::BASE_URL;
    }
}
