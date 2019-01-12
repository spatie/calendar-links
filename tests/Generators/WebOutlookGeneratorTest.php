<?php

namespace Spatie\CalendarLink\Test;

use Spatie\CalendarLinks\Test\TestCase;

class WebOutlookGeneratorTest extends TestCase
{
    /** @test */
    public function it_can_generate_a_web_outlook_link()
    {
        $this->assertMatchesSnapshot(
            $this->createLink()->webOutlook()
        );
    }

    /** @test */
    public function it_can_generate_a_web_outlook_allDay_link()
    {
        $this->assertMatchesSnapshot(
            $this->createAlldayLink()->webOutlook()
        );
    }
}
