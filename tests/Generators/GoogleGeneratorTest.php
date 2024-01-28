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
}
