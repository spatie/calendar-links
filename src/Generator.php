<?php

declare(strict_types=1);

namespace Spatie\CalendarLinks;

interface Generator
{
    /**
     * Generate a URL to add event to calendar.
     * @return non-empty-string
     */
    public function generate(Link $link): string;
}
