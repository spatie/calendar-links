<?php

namespace Spatie\CalendarLink\Test;

use Spatie\CalendarLinks\Test\TestCase;

class GoogleGeneratorTest extends TestCase
{
    /** @test */
    public function it_can_generate_a_google_link()
    {
        $this->assertMatchesSnapshot(
            $this->createLink()->google()
        );
    }

    /** @test */
    public function it_can_generate_a_google_allDay_link()
    {
        $this->assertMatchesSnapshot(
            $this->createAlldayLink()->google()
        );
    }
}
