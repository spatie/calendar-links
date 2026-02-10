<?php

declare(strict_types=1);

namespace Spatie\CalendarLinks\Tests\Generators;

use PHPUnit\Framework\Attributes\Test;
use Spatie\CalendarLinks\Generator;

/** @mixin \Spatie\CalendarLinks\Tests\TestCase */
trait GeneratorTestContract
{
    abstract protected function generator(): Generator;

    abstract protected function linkMethodName(): string;

    #[Test]
    public function it_can_generate_a_short_event_link(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator()->generate($this->createShortEventLink())
        );
    }

    #[Test]
    public function it_can_generate_a_single_day_allday_event_link(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator()->generate($this->createSingleDayAllDayEventLink())
        );
    }

    #[Test]
    public function it_can_generate_a_multiple_days_event_link(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator()->generate($this->createMultipleDaysEventLink())
        );
    }

    #[Test]
    public function it_can_generate_a_multiple_days_event_link_with_allday_flag(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator()->generate($this->createMultipleDaysAllDayEventLink())
        );
    }

    #[Test]
    public function it_can_generate_a_description_is_html_code_event_link_with_allday_flag(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator()->generate($this->createDescriptionIsHtmlCodeEventLink())
        );
    }

    #[Test]
    public function it_correctly_generates_all_day_events_by_days(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator()->generate($this->createAllDayEventMultipleDaysWithTimezoneLink())
        );
    }

    #[Test]
    public function it_correctly_generates_all_day_events_by_dates(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator()->generate($this->createEventMultipleDaysViaStartEndWithTimezoneLink())
        );
    }

    #[Test]
    public function it_correctly_generates_all_day_events_by_dates_diff_tz(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator()->generate($this->createEventMultipleDaysViaStartEndWithDiffTimezoneLink())
        );
    }
}
