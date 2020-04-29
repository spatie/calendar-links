<?php

namespace Spatie\CalendarLink\Test;

use DateTime;
use DateTimeZone;
use Spatie\CalendarLinks\Link;
use Spatie\CalendarLinks\Test\TestCase;

class YahooGeneratorTest extends TestCase
{
    /** @test */
    public function it_can_generate_a_yahoo_link()
    {
        $this->assertMatchesSnapshot(
            $this->createLink()->yahoo()
        );
    }

    /** @test */
    public function it_can_generate_a_yahoo_multiple_days_link()
    {
        $this->assertMatchesSnapshot(
            $this->createMultipleDaysLink()->yahoo()
        );
    }

    /** @test */
    public function it_can_generate_a_yahoo_link_for_long_multiple_days_event()
    {
        $link = Link::create(
            'Christmas and New Year',
            DateTime::createFromFormat('Y-m-d H:i', '2019-12-25 23:00', new DateTimeZone('UTC')),
            DateTime::createFromFormat('Y-m-d H:i', '2020-01-01 1:00', new DateTimeZone('UTC'))
        )->description('Long event')->address('Party Lane 1A, 1337 Funtown');

        $this->assertMatchesSnapshot($link->yahoo());
    }

    /** @test */
    public function it_can_generate_a_yahoo_allDay_link()
    {
        $this->assertMatchesSnapshot(
            $this->createAlldayLink()->yahoo()
        );
    }
}
