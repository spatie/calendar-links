<?php

namespace Spatie\CalendarLinks\Tests\Generators;

use Spatie\CalendarLinks\Generator;
use Spatie\CalendarLinks\Generators\WebOffice365;
use Spatie\CalendarLinks\Tests\TestCase;

class WebOffice365GeneratorTest extends TestCase
{
    use GeneratorTestContract;

    protected function generator(): Generator
    {
        return new WebOffice365();
    }

    protected function linkMethodName(): string
    {
        return 'webOffice365';
    }
}
