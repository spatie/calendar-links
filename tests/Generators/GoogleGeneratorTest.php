<?php

declare(strict_types=1);

namespace Spatie\CalendarLinks\Tests\Generators;

use DateTime;
use DateTimeZone;
use Spatie\CalendarLinks\Generator;
use Spatie\CalendarLinks\Generators\Google;
use Spatie\CalendarLinks\Link;
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

    /** @test */
    public function it_includes_timezone_for_non_all_day_events(): void
    {
        $link = new Link(
            'Birthday',
            (new DateTime('2018-02-01 09:00'))->setTimezone(new DateTimeZone('Europe/Brussels')),
            (new DateTime('2018-02-01 18:00'))->setTimezone(new DateTimeZone('Europe/Brussels'))
        );

        $generator = new Google();
        $url = $generator->generate($link);

        $this->assertStringContainsString('ctz=Europe/Brussels', $url);
    }

    /** @test */
    public function it_excludes_timezone_for_all_day_events(): void
    {
        $link = Link::createAllDay(
            'Birthday',
            new DateTime('2018-02-01')
        );

        $generator = new Google();
        $url = $generator->generate($link);

        $this->assertStringNotContainsString('ctz=', $url);
    }
}
