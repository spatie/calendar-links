<?php

declare(strict_types=1);

namespace Spatie\CalendarLinks\Generators;

/** @api */
final class WebOffice extends BaseOutlook
{
    /** @var non-empty-string */
    private const string BASE_URL = 'https://outlook.cloud.microsoft/calendar/deeplink/compose?path=/calendar/action/compose&rru=addevent';

    /** @inheritDoc */
    #[\Override]
    protected function baseUrl(): string
    {
        return static::BASE_URL;
    }
}
