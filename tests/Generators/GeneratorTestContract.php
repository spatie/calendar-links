<?php

declare(strict_types=1);

namespace Spatie\CalendarLinks\Tests\Generators;

use Spatie\CalendarLinks\Generator;

/** @mixin \Spatie\CalendarLinks\Tests\TestCase */
trait GeneratorTestContract
{
    abstract protected function generator(): Generator;

    abstract protected function linkMethodName(): string;

    /** @test */
    public function it_can_generate_a_short_event_link(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator()->generate($this->createShortEventLink())
        );
    }

    /** @test */
    public function it_can_generate_a_single_day_allday_event_link(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator()->generate($this->createSingleDayAllDayEventLink())
        );
    }

    /** @test */
    public function it_can_generate_a_multiple_days_event_link(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator()->generate($this->createMultipleDaysEventLink())
        );
    }

    /** @test */
    public function it_can_generate_a_multiple_days_event_link_with_allday_flag(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator()->generate($this->createMultipleDaysAllDayEventLink())
        );
    }

    /** @test */
    public function it_can_generate_a_description_is_html_code_event_link_with_allday_flag(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator()->generate($this->createDescriptionIsHtmlCodeEventLink())
        );
    }

    /** @test */
    public function it_correctly_generates_all_day_events_by_days(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator()->generate($this->createAllDayEventMultipleDaysWithTimezoneLink())
        );
    }

    /** @test */
    public function it_correctly_generates_all_day_events_by_dates(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator()->generate($this->createEventMultipleDaysViaStartEndWithTimezoneLink())
        );
    }

    /** @test */
    public function it_correctly_generates_all_day_events_by_dates_diff_tz(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator()->generate($this->createEventMultipleDaysViaStartEndWithDiffTimezoneLink())
        );
    }
}
