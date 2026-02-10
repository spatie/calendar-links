<?php

declare(strict_types=1);

namespace Spatie\CalendarLinks\Generators;

final class WebOffice extends BaseOutlook
{
    /** @var non-empty-string */
    private const BASE_URL = 'https://outlook.office.com/calendar/deeplink/compose?path=/calendar/action/compose&rru=addevent';

    /** @inheritDoc */
    #[\Override]
    protected function baseUrl(): string
    {
        return static::BASE_URL;
    }
}
