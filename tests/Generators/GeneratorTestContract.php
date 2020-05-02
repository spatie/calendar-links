<?php

declare(strict_types=1);

namespace Spatie\CalendarLinks\Test\Generators;

use Spatie\CalendarLinks\Generator;

/** @mixin \Spatie\CalendarLinks\Test\TestCase */
trait GeneratorTestContract
{
    abstract protected function generator(): Generator;

    abstract protected function linkMethodName(): string;

    /** @test */
    public function it_has_a_valid_method_in_link_class()
    {
        $link = $this->createShortEventLink();

        $this->assertSame($this->generator()->generate($link), $link->{$this->linkMethodName()}());
    }

    /** @test */
    public function it_can_generate_a_short_event_link()
    {
        $this->assertMatchesSnapshot(
            $this->generator()->generate($this->createShortEventLink())
        );
    }

    /** @test */
    public function it_can_generate_an_all_day_event_link()
    {
        $this->assertMatchesSnapshot(
            $this->generator()->generate($this->createAllDayEventLink())
        );
    }

    /** @test */
    public function it_can_generate_a_multiple_days_event_link()
    {
        $this->assertMatchesSnapshot(
            $this->generator()->generate($this->createMultipleDaysEventLink())
        );
    }
}
