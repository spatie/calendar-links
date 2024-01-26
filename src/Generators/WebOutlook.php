<?php

namespace Spatie\CalendarLinks\Generators;

class WebOutlook extends BaseOutlook
{
    protected const BASE_URL = 'https://outlook.live.com/calendar/action/compose?path=/calendar/action/compose&rru=addevent';

    /** @inheritDoc */
    public function baseUrl(): string
    {
        return static::BASE_URL;
    }
}
