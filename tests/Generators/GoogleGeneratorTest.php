<?php

declare(strict_types=1);

namespace Spatie\CalendarLinks\Tests\Generators;

use DateTime;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\CalendarLinks\Generator;
use Spatie\CalendarLinks\Generators\Google;
use Spatie\CalendarLinks\Link;
use Spatie\CalendarLinks\Tests\TestCase;

final class GoogleGeneratorTest extends TestCase
{
    use GeneratorTestContract;

    #[\Override]
    protected function generator(): Generator
    {
        return new Google();
    }

    #[Test]
    public function it_can_generate_an_url_with_separate_start_and_end_timezones(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator()->generate($this->createFlightWithDistinctTimezonesLink())
        );
    }

    #[Test]
    public function it_does_not_emit_ctz_alongside_stz_and_etz(): void
    {
        // stz takes priority over ctz, so emitting both would only invite confusion.
        $url = $this->generator()->generate($this->createFlightWithDistinctTimezonesLink());

        $this->assertStringContainsString('&stz=Asia/Tokyo&etz=America/Los_Angeles', $url);
        $this->assertStringNotContainsString('ctz=', $url);
    }

    /** @param non-empty-string $timezone */
    #[Test]
    #[TestWith(['+02:00', '20260101T080000Z/20260101T090000Z'])]
    #[TestWith(['-05:00', '20260101T150000Z/20260101T160000Z'])]
    #[TestWith(['Etc/GMT+5', '20260101T150000Z/20260101T160000Z'])]
    #[TestWith(['CEST', '20260101T080000Z/20260101T090000Z'])]
    public function it_falls_back_to_utc_for_a_timezone_google_cannot_resolve(string $timezone, string $expectedDates): void
    {
        // Google ignores a ctz it cannot resolve, so local times would be read in the viewer's zone.
        $url = $this->generator()->generate($this->createEventLinkInTimezone($timezone));

        $this->assertStringContainsString('&dates='.$expectedDates, $url);
        $this->assertStringNotContainsString('ctz=', $url);
    }

    #[Test]
    public function it_falls_back_to_utc_when_only_one_end_of_a_flight_names_a_zone(): void
    {
        // Naming just the departure would leave the arrival to be read in the viewer's zone, so the
        // pair goes to UTC together.
        $url = $this->generator()->generate($this->createFlightWithUnresolvableEndTimezoneLink());

        $this->assertStringContainsString('&dates=20270315T000000Z/20270315T163000Z', $url);
        $this->assertStringNotContainsString('stz=', $url);
        $this->assertStringNotContainsString('etz=', $url);
        $this->assertStringNotContainsString('ctz=', $url);
    }

    #[Test]
    public function it_keeps_the_calendar_dates_of_an_all_day_event_in_an_unresolvable_timezone(): void
    {
        // An all-day event has no clock time to convert, so only the zone naming is dropped.
        $link = Link::createAllDay('Holiday', new DateTime('2026-01-01 00:00', new DateTimeZone('+02:00')));

        $url = $this->generator()->generate($link);

        $this->assertStringContainsString('&dates=20260101/20260102', $url);
        $this->assertStringNotContainsString('ctz=', $url);
    }

    #[Test]
    public function it_emits_a_single_timezone_for_two_spellings_of_utc(): void
    {
        $url = $this->generator()->generate($this->createEventAcrossUtcAliasesLink());

        $this->assertStringContainsString('&ctz=UTC', $url);
        $this->assertStringNotContainsString('stz=', $url);
        $this->assertStringNotContainsString('etz=', $url);
    }

    #[Test]
    public function it_emits_a_single_timezone_for_a_z_suffixed_start(): void
    {
        $url = $this->generator()->generate($this->createEventWithZSuffixedStartLink());

        // `Z` is still not an identifier Google accepts, but the event is at least no longer
        // described as crossing a zone. Naming it properly is the subject of #234.
        $this->assertStringNotContainsString('stz=', $url);
        $this->assertStringNotContainsString('etz=', $url);
    }

    #[Test]
    public function it_still_names_both_zones_when_two_places_share_an_offset(): void
    {
        $url = $this->generator()->generate($this->createFlightBetweenPlacesSharingAnOffsetLink());

        $this->assertStringContainsString('&stz=Europe/London&etz=Europe/Lisbon', $url);
    }

    #[Test]
    public function it_can_generate_an_url_with_custom_parameters(): void
    {
        $link = $this->createShortEventLink();

        $this->assertMatchesSnapshot($link->google(['recur' => 'RRULE:FREQ=DAILY']));
    }

    #[Test]
    public function it_can_generate_an_url_with_repeated_parameters(): void
    {
        $link = $this->createShortEventLink();

        $this->assertMatchesSnapshot($link->google([
            'sprop' => [
                'goo.allowModify:false',
                'goo.allowInvitesOther:false',
                'goo.showInvitees:false',
            ],
            'vcon' => 'meet',
        ]));
    }

    #[Test]
    public function it_omits_a_repeated_parameter_without_values(): void
    {
        $link = $this->createShortEventLink();

        $this->assertMatchesSnapshot($link->google(['sprop' => [], 'vcon' => 'meet']));
    }

    #[Test]
    public function it_keeps_a_description_and_address_of_zero(): void
    {
        // '0' is falsy in PHP, so only an empty string may drop these fields.
        $link = $this->createShortEventLink()->description('0')->address('0');

        $url = $this->generator()->generate($link);

        $this->assertStringContainsString('&details=0', $url);
        $this->assertStringContainsString('&location=0', $url);
    }
}
