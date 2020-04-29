<?php

namespace Spatie\CalendarLinks;

interface Generator
{
    /**
     * Generate an URL to add event to calendar.
     * @param \Spatie\CalendarLinks\Link $link
     * @return string
     */
    public function generate(Link $link): string;
}
