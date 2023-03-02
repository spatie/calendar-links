<?php declare(strict_types=1);

namespace Spatie\CalendarLinks\Tests\Generators;

use Spatie\CalendarLinks\Generator;
use Spatie\CalendarLinks\Generators\WebOffice;
use Spatie\CalendarLinks\Tests\TestCase;

final class WebOfficeGeneratorTest extends TestCase
{
    use GeneratorTestContract;

    protected function generator(): Generator
    {
        return new WebOffice();
    }

    protected function linkMethodName(): string
    {
        return 'webOffice';
    }
}
