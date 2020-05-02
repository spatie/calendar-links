<?php

namespace Spatie\CalendarLink\Test;

use Spatie\CalendarLinks\Generator;
use Spatie\CalendarLinks\Generators\WebOutlook;
use Spatie\CalendarLinks\Test\Generators\GeneratorTestContract;
use Spatie\CalendarLinks\Test\TestCase;

class WebOutlookGeneratorTest extends TestCase
{
    use GeneratorTestContract;

    protected function generator(): Generator
    {
        return new WebOutlook();
    }

    protected function linkMethodName(): string
    {
        return 'webOutlook';
    }
}
