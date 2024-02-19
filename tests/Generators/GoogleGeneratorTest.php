<?php declare(strict_types=1);

namespace Spatie\CalendarLinks\Tests\Generators;

use Spatie\CalendarLinks\Generator;
use Spatie\CalendarLinks\Generators\Google;
use Spatie\CalendarLinks\Tests\TestCase;

final class GoogleGeneratorTest extends TestCase
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

    /** @test */
    public function it_can_generate_an_url_with_custom_parameters(): void
    {
        $link = $this->createShortEventLink();

        $this->assertMatchesSnapshot($link->google(['recur' => 'RRULE:FREQ=DAILY']));
    }
}
