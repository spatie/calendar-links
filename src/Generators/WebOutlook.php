<?php declare(strict_types=1);

namespace Spatie\CalendarLinks\Generators;

class WebOutlook extends BaseOutlook
{
    /** @var non-empty-string */
    protected const BASE_URL = 'https://outlook.live.com/calendar/action/compose?path=/calendar/action/compose&rru=addevent';

    /** @inheritDoc */
    public function baseUrl(): string
    {
        return static::BASE_URL;
    }
}
