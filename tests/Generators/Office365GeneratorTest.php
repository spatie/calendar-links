<?php

namespace Spatie\CalendarLinks\Tests\Generators;

use Spatie\CalendarLinks\Generator;
use Spatie\CalendarLinks\Generators\Office365;
use Spatie\CalendarLinks\Tests\TestCase;

class Office365GeneratorTest extends TestCase
{
    use GeneratorTestContract;

    protected function generator(): Generator
    {
        return new Office365();
    }

    protected function linkMethodName(): string
    {
        return 'office365';
    }
}
