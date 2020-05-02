<?php

namespace Spatie\CalendarLink\Test;

use Spatie\CalendarLinks\Generator;
use Spatie\CalendarLinks\Generators\Google;
use Spatie\CalendarLinks\Test\Generators\GeneratorTestContract;
use Spatie\CalendarLinks\Test\TestCase;

class GoogleGeneratorTest extends TestCase
{
    use GeneratorTestContract;

    protected function generator(): Generator
    {
        return new Google();
    }

    protected function linkMethodName(): string
    {
        return 'google';
    }
}
