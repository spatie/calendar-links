<?php

namespace Spatie\CalendarLinks\Tests\Generators;

use Spatie\CalendarLinks\Generator;
use Spatie\CalendarLinks\Generators\Google;
use Spatie\CalendarLinks\Tests\TestCase;

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
