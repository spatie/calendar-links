<?php

declare(strict_types=1);

namespace Spatie\CalendarLinks\Tests;

use DateTime;
use DateTimeZone;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Spatie\CalendarLinks\Link;
use Spatie\Snapshots\MatchesSnapshots;

abstract class TestCase extends BaseTestCase
{
    use MatchesSnapshots;

    protected function createShortEventLink(): Link
    {
        $description = 'With balloons, clowns and stuff
Bring a dog, bring a frog';

        return Link::create(
            'Birthday',
            DateTime::class::createFromFormat('Y-m-d H:i', '2018-02-01 09:00', new DateTimeZone('UTC')),
            DateTime::createFromFormat('Y-m-d H:i', '2018-02-01 18:00', new DateTimeZone('UTC'))
        )->description($description)->address('Party Lane 1A, 1337 Funtown');
    }

    protected function createMultipleDaysEventLink(): Link
    {
        $description = 'With balloons, clowns and stuff
Bring a dog, bring a frog';

        return Link::create(
            'New Year',
            DateTime::createFromFormat('Y-m-d H:i', '2019-12-31 23:00', new DateTimeZone('UTC')),
            DateTime::createFromFormat('Y-m-d H:i', '2020-01-01 1:00', new DateTimeZone('UTC'))
        )->description($description)->address('Party Lane 1A, 1337 Funtown');
    }

    protected function createSingleDayAllDayEventLink(): Link
    {
        $description = 'With balloons, clowns and stuff
Bring a dog, bring a frog';

        return Link::createAllDay(
            'Birthday',
            DateTime::createFromFormat('Y-m-d', '2018-02-01', new DateTimeZone('UTC'))->setTime(0, 0)
        )->description($description)->address('Party Lane 1A, 1337 Funtown');
    }

    protected function createMultipleDaysAllDayEventLink(): Link
    {
        $description = 'With balloons, clowns and stuff
Bring a dog, bring a frog';


        return Link::createAllDay(
            'Birthday',
            DateTime::createFromFormat('Y-m-d', '2018-02-01', new DateTimeZone('UTC'))->setTime(0, 0),
            5
        )->description($description)->address('Party Lane 1A, 1337 Funtown');
    }

    protected function createAllDayEventMultipleDaysWithTimezoneLink(): Link
    {
        $description = 'Testing all day';

        return Link::createAllDay(
            'All day bugs',
            DateTime::createFromFormat('Y-m-d', '2024-01-25', new DateTimeZone('Pacific/Wake'))->setTime(0, 0),
            5
        )->description($description);
    }

    protected function createEventMultipleDaysViaStartEndWithTimezoneLink(): Link
    {
        $description = 'Testing all day';

        return Link::createAllDay(
            'All day bugs',
            \DateTime::createFromFormat('Y-m-d', '2024-01-25', new DateTimeZone('Pacific/Wake'))->setTime(0, 0),
            6,
        )->description($description);
    }

    protected function createEventMultipleDaysViaStartEndWithDiffTimezoneLink(): Link
    {
        // The end date is read as the calendar date the caller wrote, so the last day is 2024-01-30
        // and the event runs for 6 days. Converting the instant instead would land on 2024-01-31
        // Pacific/Wake and add a seventh day the caller never asked for.
        return (new Link(
            'All day bugs',
            DateTime::createFromFormat('Y-m-d', '2024-01-25', new DateTimeZone('Pacific/Wake'))->setTime(0, 0),
            DateTime::createFromFormat('Y-m-d', '2024-01-30', new DateTimeZone('Europe/Luxembourg'))->setTime(13, 00),
            true,
        ));
    }

    protected function createFlightWithDistinctTimezonesLink(): Link
    {
        // Departs 09:00 in Tokyo and lands 09:30 the same calendar day in Los Angeles, 16.5 hours later.
        return Link::create(
            'NH 106 Tokyo to Los Angeles',
            new DateTime('2027-03-15 09:00', new DateTimeZone('Asia/Tokyo')),
            new DateTime('2027-03-15 09:30', new DateTimeZone('America/Los_Angeles')),
        );
    }

    protected function createEventAcrossUtcAliasesLink(): Link
    {
        // Two spellings of one zone, so the event never leaves UTC.
        return Link::create(
            'Meeting',
            new DateTime('2026-01-01 10:00', new DateTimeZone('UTC')),
            new DateTime('2026-01-01 11:00', new DateTimeZone('Etc/UTC')),
        );
    }

    protected function createEventWithZSuffixedStartLink(): Link
    {
        // PHP names the zone of a Z suffixed ISO string literally `Z`, which no calendar service accepts.
        return Link::create(
            'Meeting',
            new DateTime('2026-01-01T10:00:00Z'),
            new DateTime('2026-01-01 11:00', new DateTimeZone('UTC')),
        );
    }

    protected function createFlightBetweenPlacesSharingAnOffsetLink(): Link
    {
        // London and Lisbon are both on +00:00 in January, yet they are two places worth naming.
        return Link::create(
            'BA 500 London to Lisbon',
            new DateTime('2026-01-15 09:00', new DateTimeZone('Europe/London')),
            new DateTime('2026-01-15 11:45', new DateTimeZone('Europe/Lisbon')),
        );
    }

    protected function createFlightBookedWithALegacyZoneAliasLink(): Link
    {
        // `Japan` is an IANA backward alias for Asia/Tokyo. Both ends sit on +09:00 all year, so
        // only the two zone names tell Tokyo from Seoul.
        return Link::create(
            'NH 867 Tokyo to Seoul',
            new DateTime('2026-01-15 09:00', new DateTimeZone('Japan')),
            new DateTime('2026-01-15 11:45', new DateTimeZone('Asia/Seoul')),
        );
    }

    protected function createEventWithGuestsLink(): Link
    {
        return Link::create(
            'Birthday',
            DateTime::createFromFormat('Y-m-d H:i', '2018-02-01 09:00', new DateTimeZone('UTC')),
            DateTime::createFromFormat('Y-m-d H:i', '2018-02-01 18:00', new DateTimeZone('UTC'))
        )
            ->address('Party Lane 1A, 1337 Funtown')
            ->guest('santa@example.com')
            ->guests(['easter.bunny@example.com', 'tooth.fairy@example.com'])
            ->guest('krampus@example.com', optional: true);
    }

    protected function createDescriptionIsHtmlCodeEventLink(): Link
    {
        $description = 'With balloons, clowns and stuff
Bring a dog, bring a frog.
There will be line breaks on it.
Project link <a href="https://github.com/spatie/calendar-links">calendar-links</a>
<img src="https://github-ads.s3.eu-central-1.amazonaws.com/calendar-links.jpg?t=1" width="419px" />
<br>
Thank you.
';

        return Link::create(
            'Birthday Party +1',
            DateTime::createFromFormat('Y-m-d H:i', '2018-02-01 09:00', new DateTimeZone('UTC')),
            DateTime::createFromFormat('Y-m-d H:i', '2018-02-01 18:00', new DateTimeZone('UTC'))
        )->description($description)->address('Party Lane 1A, 1337 Funtown');
    }
}
