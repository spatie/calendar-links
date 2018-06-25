<?php

namespace Spatie\CalendarLink\Test;

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
    public function it_can_generate_a_yahoo_allDay_link()
    {
        $this->assertMatchesSnapshot(
            $this->createAlldayLink()->yahoo()
        );
    }
}
