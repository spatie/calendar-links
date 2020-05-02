<?php

namespace Spatie\CalendarLinks\Tests\Generators;

use Spatie\CalendarLinks\Generator;
use Spatie\CalendarLinks\Generators\WebOutlook;
use Spatie\CalendarLinks\Tests\TestCase;

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
