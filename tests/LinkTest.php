<?php

declare(strict_types=1);

namespace Spatie\CalendarLinks\Tests;

use DateTime;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\CalendarLinks\Exceptions\InvalidLink;
use Spatie\CalendarLinks\Generators\Ics;
use Spatie\CalendarLinks\Link;

final class LinkTest extends TestCase
{
    #[Test]
    public function it_is_initializable(): void
    {
        $this->assertInstanceOf(Link::class, $this->createShortEventLink());
    }

    #[Test]
    public function it_will_throw_an_exception_when_to_comes_after_from(): void
    {
        $this->expectException(InvalidLink::class);

        new Link(
            'Birthday',
            DateTime::createFromFormat('Y-m-d H:i', '2018-02-01 18:00'),
            DateTime::createFromFormat('Y-m-d H:i', '2018-02-01 09:00')
        );
    }

    #[Test]
    public function it_has_a_title(): void
    {
        $this->assertEquals('Birthday', $this->createShortEventLink()->title);
    }

    #[Test]
    public function it_has_a_mutable_from_date(): void
    {
        $this->assertEquals(new DateTime('20180201T090000 UTC'), $this->createShortEventLink()->from);
    }

    #[Test]
    public function it_has_a_mutable_to_date(): void
    {
        $this->assertEquals(new DateTime('20180201T180000 UTC'), $this->createShortEventLink()->to);
    }

    #[Test]
    public function it_has_an_immutable_from_date(): void
    {
        $this->assertEquals(new DateTime('20180201T090000 UTC'), $this->createShortEventLink()->from);
    }

    #[Test]
    public function it_has_an_immutable_to_date(): void
    {
        $this->assertEquals(new \DateTimeImmutable('20180201T180000 UTC'), $this->createShortEventLink()->to);
    }

    #[Test]
    public function it_can_have_a_description(): void
    {
        $link = $this->createShortEventLink();
        $correctDescription = 'With balloons, clowns and stuff
Bring a dog, bring a frog';
        $this->assertEquals($correctDescription, $link->description);
    }

    #[Test]
    public function it_can_have_an_address(): void
    {
        $link = $this->createShortEventLink();

        $this->assertEquals('Party Lane 1A, 1337 Funtown', $link->address);
    }

    #[Test]
    public function it_keeps_both_timezones_of_a_cross_timezone_event(): void
    {
        $flight = $this->createFlightWithDistinctTimezonesLink();

        $this->assertSame('Asia/Tokyo', $flight->fromTimezone->getName());
        $this->assertSame('America/Los_Angeles', $flight->toTimezone->getName());
        $this->assertTrue($flight->hasDistinctTimezones());
    }

    #[Test]
    public function it_still_normalises_the_end_date_into_the_start_timezone(): void
    {
        $flight = $this->createFlightWithDistinctTimezonesLink();

        // Recording the zones must not change $from or $to themselves.
        $this->assertSame('Asia/Tokyo', $flight->to->getTimezone()->getName());
        $this->assertSame('2027-03-16T01:30:00+09:00', $flight->to->format('c'));
        $this->assertSame('2027-03-15T09:30:00-07:00', $flight->to->setTimezone($flight->toTimezone)->format('c'));
    }

    /** @param non-empty-string $timezone */
    #[Test]
    #[TestWith(['UTC'])]
    #[TestWith(['Asia/Tokyo'])]
    #[TestWith(['Europe/Brussels'])]
    #[TestWith(['US/Pacific'])]
    public function it_reports_a_region_name_as_resolvable(string $timezone): void
    {
        $this->assertTrue($this->createEventLinkInTimezone($timezone)->hasResolvableTimezones());
    }

    /** @param non-empty-string $timezone */
    #[Test]
    #[TestWith(['+02:00'])]
    #[TestWith(['-05:00'])]
    #[TestWith(['CEST'])]
    #[TestWith(['GMT'])]
    #[TestWith(['EST'])]
    #[TestWith(['Etc/GMT+5'])]
    #[TestWith(['Etc/UTC'])]
    public function it_does_not_report_an_offset_or_abbreviation_as_resolvable(string $timezone): void
    {
        $this->assertFalse($this->createEventLinkInTimezone($timezone)->hasResolvableTimezones());
    }

    #[Test]
    public function it_does_not_report_resolvable_timezones_when_only_one_end_names_a_region(): void
    {
        // Naming one end and not the other would leave the pair inconsistent, so both go to UTC.
        $this->assertFalse($this->createFlightWithUnresolvableEndTimezoneLink()->hasResolvableTimezones());
        $this->assertTrue($this->createFlightWithDistinctTimezonesLink()->hasResolvableTimezones());
    }

    #[Test]
    public function it_does_not_report_distinct_timezones_for_a_single_zone_event(): void
    {
        $this->assertFalse($this->createShortEventLink()->hasDistinctTimezones());
    }

    #[Test]
    public function it_does_not_report_distinct_timezones_for_an_all_day_event(): void
    {
        // An all-day event has no clock time to place in a zone, whatever it was given.
        $this->assertFalse($this->createEventMultipleDaysViaStartEndWithDiffTimezoneLink()->hasDistinctTimezones());
    }

    #[Test]
    public function it_does_not_report_distinct_timezones_for_two_spellings_of_utc(): void
    {
        $this->assertFalse($this->createEventAcrossUtcAliasesLink()->hasDistinctTimezones());
    }

    #[Test]
    public function it_does_not_report_distinct_timezones_for_a_z_suffixed_start(): void
    {
        $link = $this->createEventWithZSuffixedStartLink();

        $this->assertSame('Z', $link->fromTimezone->getName());
        $this->assertFalse($link->hasDistinctTimezones());
    }

    #[Test]
    public function it_does_not_report_distinct_timezones_for_an_offset_matching_the_start_zone(): void
    {
        // A bare offset names no place, so it adds nothing to the zone it is paired with.
        $link = Link::create(
            'Meeting',
            new DateTime('2026-01-01 10:00', new DateTimeZone('UTC')),
            new DateTime('2026-01-01T11:00:00+00:00'),
        );

        $this->assertFalse($link->hasDistinctTimezones());
    }

    #[Test]
    public function it_reports_distinct_timezones_for_two_places_sharing_an_offset(): void
    {
        // Equal offsets are not enough to merge two real places: the destination is still worth naming.
        $flight = $this->createFlightBetweenPlacesSharingAnOffsetLink();

        $this->assertSame(0, $flight->fromTimezone->getOffset($flight->from));
        $this->assertSame(0, $flight->toTimezone->getOffset($flight->to));
        $this->assertTrue($flight->hasDistinctTimezones());
    }

    #[Test]
    public function it_reports_distinct_timezones_for_a_legacy_alias_naming_a_place(): void
    {
        $flight = $this->createFlightBookedWithALegacyZoneAliasLink();

        $this->assertSame(32400, $flight->fromTimezone->getOffset($flight->from));
        $this->assertSame(32400, $flight->toTimezone->getOffset($flight->to));
        $this->assertTrue($flight->hasDistinctTimezones());
    }

    #[Test]
    public function it_reports_distinct_timezones_for_every_legacy_alias_sharing_an_offset(): void
    {
        // The IANA backward names are ordinary places wearing an old label, and the timezone
        // database gives them the same `??` country as the UTC family, so they need naming outright.
        $pairs = [
            ['Singapore', 'Asia/Kuala_Lumpur'],
            ['Poland', 'Europe/Berlin'],
            ['Hongkong', 'Asia/Shanghai'],
            ['US/Eastern', 'America/Toronto'],
            ['GB', 'Europe/Lisbon'],
        ];

        foreach ($pairs as [$from, $to]) {
            $link = Link::create(
                'Flight',
                new DateTime('2026-01-15 09:00', new DateTimeZone($from)),
                new DateTime('2026-01-15 11:45', new DateTimeZone($to)),
            );

            $this->assertSame(
                $link->fromTimezone->getOffset($link->from),
                $link->toTimezone->getOffset($link->to),
                "$from and $to should share an offset, otherwise this proves nothing",
            );
            $this->assertTrue($link->hasDistinctTimezones(), "$from to $to should keep both zones");
        }
    }

    #[Test]
    public function it_reports_distinct_timezones_for_a_posix_rule_zone(): void
    {
        // EST5EDT names no place, but it carries daylight saving rules rather than one fixed offset,
        // so it counts as a zone of its own. Folding it away would drop America/New_York instead.
        $link = Link::create(
            'Meeting',
            new DateTime('2026-01-15 09:00', new DateTimeZone('EST5EDT')),
            new DateTime('2026-01-15 11:00', new DateTimeZone('America/New_York')),
        );

        $this->assertTrue($link->hasDistinctTimezones());
    }

    #[Test]
    public function it_does_not_report_distinct_timezones_when_only_one_side_names_a_place(): void
    {
        // Etc/GMT+5 is a fixed offset with no place of its own, so at a matching offset it adds
        // nothing to the zone opposite it.
        $link = Link::create(
            'Meeting',
            new DateTime('2026-01-15 09:00', new DateTimeZone('America/New_York')),
            new DateTime('2026-01-15 11:00', new DateTimeZone('Etc/GMT+5')),
        );

        $this->assertFalse($link->hasDistinctTimezones());
    }

    #[Test]
    public function it_does_not_report_distinct_timezones_across_a_daylight_saving_change_in_one_zone(): void
    {
        // Both endpoints are named `Europe/London`, so the zones match even though the offsets do not.
        $link = Link::create(
            'Clocks going forward',
            new DateTime('2026-03-29 00:30', new DateTimeZone('Europe/London')),
            new DateTime('2026-03-29 02:30', new DateTimeZone('Europe/London')),
        );

        $this->assertNotSame($link->fromTimezone->getOffset($link->from), $link->toTimezone->getOffset($link->to));
        $this->assertFalse($link->hasDistinctTimezones());
    }

    #[Test]
    public function it_does_not_report_distinct_timezones_for_an_all_day_event_across_utc_spellings(): void
    {
        $link = new Link(
            'New Year break',
            new DateTime('2026-01-01', new DateTimeZone('UTC')),
            new DateTime('2026-01-02', new DateTimeZone('Etc/UTC')),
            true,
        );

        $this->assertFalse($link->hasDistinctTimezones());
        $this->assertStringContainsString('dates=20260101/20260103', $link->google());
    }

    #[Test]
    public function it_keeps_the_inclusive_end_date_of_a_cross_timezone_all_day_event(): void
    {
        // New Year's Day and the day after, written by a caller whose two dates carry different zones.
        $link = new Link(
            'New Year break',
            new DateTime('2026-01-01', new DateTimeZone('America/New_York')),
            new DateTime('2026-01-02', new DateTimeZone('Europe/London')),
            true,
        );

        $this->assertStringContainsString('dates=20260101/20260103', $link->google());
        $this->assertStringContainsString('DURATION:P2D', $link->ics([], ['format' => Ics::FORMAT_FILE]));
    }

    #[Test]
    public function it_leaves_a_single_timezone_all_day_event_alone(): void
    {
        // The control for the test above: same dates, one zone, so nothing is reinterpreted.
        $link = new Link(
            'New Year break',
            new DateTime('2026-01-01', new DateTimeZone('America/New_York')),
            new DateTime('2026-01-02', new DateTimeZone('America/New_York')),
            true,
        );

        $this->assertStringContainsString('dates=20260101/20260103', $link->google());
        $this->assertStringContainsString('DURATION:P2D', $link->ics([], ['format' => Ics::FORMAT_FILE]));
    }

    #[Test]
    public function it_still_rejects_a_negative_range_across_timezones_when_all_day(): void
    {
        $this->expectException(InvalidLink::class);

        new Link(
            'New Year break',
            new DateTime('2026-01-02', new DateTimeZone('America/New_York')),
            new DateTime('2026-01-01', new DateTimeZone('Europe/London')),
            true,
        );
    }

    #[Test]
    public function it_can_have_required_and_optional_guests(): void
    {
        $link = $this->createShortEventLink()
            ->guest('santa@example.com')
            ->guest('krampus@example.com', optional: true);

        $this->assertSame([
            ['email' => 'santa@example.com', 'optional' => false],
            ['email' => 'krampus@example.com', 'optional' => true],
        ], $link->guests);
    }

    #[Test]
    public function it_can_have_several_guests_added_at_once(): void
    {
        $link = $this->createShortEventLink()
            ->guests(['santa@example.com', 'krampus@example.com'], optional: true);

        $this->assertSame([
            ['email' => 'santa@example.com', 'optional' => true],
            ['email' => 'krampus@example.com', 'optional' => true],
        ], $link->guests);
    }

    #[Test]
    public function it_ignores_a_guest_that_is_already_on_the_list(): void
    {
        $link = $this->createShortEventLink()
            ->guest('santa@example.com')
            ->guest('santa@example.com');

        $this->assertSame([
            ['email' => 'santa@example.com', 'optional' => false],
        ], $link->guests);
    }

    #[Test]
    public function it_ignores_a_guest_whose_address_differs_only_in_case(): void
    {
        $link = $this->createShortEventLink()
            ->guest('santa@example.com')
            ->guest('Santa@Example.COM');

        // The first spelling is the one that survives.
        $this->assertSame([
            ['email' => 'santa@example.com', 'optional' => false],
        ], $link->guests);
    }

    #[Test]
    public function it_keeps_the_role_a_duplicated_guest_was_first_added_with(): void
    {
        $link = $this->createShortEventLink()
            ->guest('santa@example.com')
            ->guest('santa@example.com', optional: true);

        $this->assertSame([
            ['email' => 'santa@example.com', 'optional' => false],
        ], $link->guests);
    }

    #[Test]
    public function it_ignores_a_duplicate_inside_a_bulk_of_guests(): void
    {
        $link = $this->createShortEventLink()
            ->guest('santa@example.com')
            ->guests(['krampus@example.com', 'KRAMPUS@example.com', 'santa@example.com']);

        $this->assertSame([
            ['email' => 'santa@example.com', 'optional' => false],
            ['email' => 'krampus@example.com', 'optional' => false],
        ], $link->guests);
    }

    #[Test]
    public function it_will_throw_an_exception_for_an_invalid_guest_email(): void
    {
        $this->expectException(InvalidLink::class);

        $this->createShortEventLink()->guest('not-an-email');
    }

    #[Test]
    public function it_will_throw_an_exception_for_a_guest_email_with_a_display_name(): void
    {
        $this->expectException(InvalidLink::class);

        $this->createShortEventLink()->guest('Santa <santa@example.com>');
    }

    #[Test]
    public function it_will_throw_an_exception_for_a_guest_email_with_a_quoted_local_part(): void
    {
        $this->expectException(InvalidLink::class);

        // Passes FILTER_VALIDATE_EMAIL, but its comma would be read as an address separator by Google and Yahoo.
        $this->createShortEventLink()->guest('"santa,claus"@example.com');
    }

    #[Test]
    public function it_adds_no_guest_at_all_when_one_address_of_a_bulk_is_invalid(): void
    {
        $link = $this->createShortEventLink();

        try {
            $link->guests(['santa@example.com', 'not-an-email']);
        } catch (InvalidLink) {
            // Expected.
        }

        $this->assertSame([], $link->guests);
    }
}
