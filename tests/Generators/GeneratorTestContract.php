<?php

namespace Spatie\CalendarLinks\Tests\Generators;

use Spatie\CalendarLinks\Generator;

/** @mixin \Spatie\CalendarLinks\Tests\TestCase */
trait GeneratorTestContract
{
    abstract protected function generator(): Generator;

    abstract protected function linkMethodName(): string;

    /** @test */
    public function it_has_a_valid_method_in_link_class()
    {
        $link = $this->createShortEventLink();

        $this->assertTrue(method_exists($link, $this->linkMethodName()));
        $this->assertSame($this->generator()->generate($link), $link->{$this->linkMethodName()}());
    }

    /** @test */
    public function it_can_generate_a_short_event_link()
    {
        $this->assertMatchesSnapshot(
            $this->generator()->generate($this->createShortEventLink())
        );

        $this->assertSame(
            $this->generator()->generate($this->createShortEventLink(false)),
            $this->generator()->generate($this->createShortEventLink(true))
        );
    }

    /** @test */
    public function it_can_generate_an_all_day_event_link()
    {
        $this->assertMatchesSnapshot(
            $this->generator()->generate($this->createAllDayEventLink())
        );

        $this->assertSame(
            $this->generator()->generate($this->createAllDayEventLink(false)),
            $this->generator()->generate($this->createAllDayEventLink(true))
        );
    }

    /** @test */
    public function it_can_generate_a_multiple_days_event_link()
    {
        $this->assertMatchesSnapshot(
            $this->generator()->generate($this->createMultipleDaysEventLink())
        );

        $this->assertSame(
            $this->generator()->generate($this->createMultipleDaysEventLink(false)),
            $this->generator()->generate($this->createMultipleDaysEventLink(true))
        );
    }
}
