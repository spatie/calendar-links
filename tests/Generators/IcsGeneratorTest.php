<?php declare(strict_types=1);

namespace Spatie\CalendarLinks\Tests\Generators;

use DateTime;
use DateTimeZone;
use Spatie\CalendarLinks\Generator;
use Spatie\CalendarLinks\Generators\Ics;
use Spatie\CalendarLinks\Tests\TestCase;

final class IcsGeneratorTest extends TestCase
{
    use GeneratorTestContract;

    /**
     * @param array<non-empty-string, non-empty-string> $options {@see \Spatie\CalendarLinks\Generators\Ics::__construct}
     * @return \Spatie\CalendarLinks\Generator
     */
    protected function generator(array $options = [], array $presentationOptions = []): Generator
    {
        $presentationOptions['format'] ??= Ics::FORMAT_FILE;

        return new Ics($options, $presentationOptions);
    }

    protected function linkMethodName(): string
    {
        return 'ics';
    }

    /** @test */
    public function it_can_generate_an_ics_link_with_custom_uid(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator(['UID' => 'random-uid', ['format' => Ics::FORMAT_FILE]])->generate($this->createShortEventLink())
        );
    }

    /** @test */
    public function it_has_a_product_id(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator(['PRODID' => 'Spatie calendar-links'], ['format' => Ics::FORMAT_FILE])->generate($this->createShortEventLink())
        );
    }

    /** @test */
    public function it_has_a_product_dtstamp(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator(['DTSTAMP' => '20180201T090000Z'], ['format' => Ics::FORMAT_FILE])->generate($this->createShortEventLink())
        );
    }

    /** @test */
    public function it_generates_base64_encoded_link_for_html(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator([], ['format' => Ics::FORMAT_HTML])->generate($this->createShortEventLink())
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
    public function it_can_generate_with_a_default_reminder(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator(['REMINDER' => []])->generate($this->createShortEventLink())
        );
    }

    /** @test */
    public function it_can_generate_with_a_custom_reminder(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator(['REMINDER' => [
                'DESCRIPTION' => 'Party with balloons and cake!',
                'TIME' => DateTime::createFromFormat('Y-m-d H:i', '2018-02-01 08:15', new DateTimeZone('UTC')),
            ]])->generate($this->createShortEventLink())
        );
    }
}
