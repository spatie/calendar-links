<?php

declare(strict_types=1);

namespace Spatie\CalendarLinks\Tests;

use DateTime;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
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
