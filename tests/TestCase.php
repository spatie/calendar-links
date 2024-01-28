<?php declare(strict_types=1);

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
            DateTime::createFromFormat('Y-m-d', '2018-02-01', new DateTimeZone('UTC'))
        )->description($description)->address('Party Lane 1A, 1337 Funtown');
    }

    protected function createMultipleDaysAllDayEventLink(): Link
    {
        $description = 'With balloons, clowns and stuff
Bring a dog, bring a frog';


        return Link::createAllDay(
            'Birthday',
            DateTime::createFromFormat('Y-m-d', '2018-02-01', new DateTimeZone('UTC')),
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
