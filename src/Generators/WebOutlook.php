<?php declare(strict_types=1);

namespace Spatie\CalendarLinks\Generators;

final class WebOutlook extends BaseOutlook
{
    /** @var non-empty-string */
    private const BASE_URL = 'https://outlook.live.com/calendar/action/compose?path=/calendar/action/compose&rru=addevent';

    /** @inheritDoc */
    protected function baseUrl(): string
    {
        return static::BASE_URL;
    }
}
