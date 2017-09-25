<?php

namespace Spatie\CalendarLinks\Test;

use DateTime;
use Spatie\CalendarLinks\Link;
use Spatie\CalendarLinks\Exceptions\InvalidLink;

class LinkTest extends TestCase
{
    /** @test */
    public function it_is_initializable()
    {
        $this->assertInstanceOf(Link::class, $this->createLink());
    }

    /** @test */
    public function it_will_throw_an_exception_when_to_comes_after_from()
    {
        $this->expectException(InvalidLink::class);

        new Link(
            'Birthday',
            DateTime::createFromFormat('Y-m-d H:i', '2018-02-01 18:00'),
            DateTime::createFromFormat('Y-m-d H:i', '2018-02-01 09:00')
        );
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
        $link = $this->createLink();

        $this->assertEquals('With clowns and stuff', $link->description);
    }

    /** @test */
    public function it_can_have_an_address()
    {
        $link = $this->createLink();

        $this->assertEquals('Party Lane 1A, 1337 Funtown', $link->address);
    }
}
