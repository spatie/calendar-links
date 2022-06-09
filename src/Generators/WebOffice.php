<?php

namespace Spatie\CalendarLinks\Generators;

class WebOffice extends BaseOutlook
{
    protected const BASE_URL = 'https://outlook.office.com/calendar/deeplink/compose?path=/calendar/action/compose&rru=addevent';

    /** @inheritDoc */
    public function baseUrl(): string
    {
        return static::BASE_URL;
    }
}
