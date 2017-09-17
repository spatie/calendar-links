<?php

namespace Spatie\CalendarLinks\Test;

use DateTime;
use Spatie\CalendarLinks\Link;
use Spatie\Snapshots\MatchesSnapshots;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use MatchesSnapshots;

    protected function createLink(): Link
    {
        return new Link(
            'Birthday',
            DateTime::createFromFormat('Y-m-d H:i', '2018-02-01 09:00'),
            DateTime::createFromFormat('Y-m-d H:i', '2018-02-01 18:00')
        );
    }
}
