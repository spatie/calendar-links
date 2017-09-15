<?php

namespace Spatie\CalendarLinks\Test;

use Spatie\CalendarLinks\Link;

class LinkTest extends TestCase
{
    /** @test */
    public function it_is_initializable()
    {
        $this->assertInstanceOf(Link::class, $this->createLink());
    }

    /** @test */
    public function it_has_a_title()
    {
        $this->assertEquals('Birthday', $this->createLink()->title);
    }

    /** @test */
    public function it_has_a_from_date()
    {
        $this->assertEquals('2018-02-01 09:00', $this->createLink()->from->format('Y-m-d H:i'));
    }

    /** @test */
    public function it_has_a_to_date()
    {
        $this->assertEquals('2018-02-01 18:00', $this->createLink()->to->format('Y-m-d H:i'));
    }

    /** @test */
    public function it_can_have_a_description()
    {
        $link = $this->createLink()->description('With clowns and stuff');

        $this->assertEquals('With clowns and stuff', $link->description);
    }

    /** @test */
    public function it_can_have_an_address()
    {
        $link = $this->createLink()->address('Party Lane 1A, 1337 Funtown');

        $this->assertEquals('Party Lane 1A, 1337 Funtown', $link->address);
    }
}
