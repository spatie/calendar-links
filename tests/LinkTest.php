<?php

namespace Spatie\CalendarLinks\Tests;

use DateTime;
use Spatie\CalendarLinks\Exceptions\InvalidLink;
use Spatie\CalendarLinks\Link;

class LinkTest extends TestCase
{
    /** @test */
    public function it_is_initializable()
    {
        $this->assertInstanceOf(Link::class, $this->createShortEventLink());
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
        $this->assertEquals('Birthday', $this->createShortEventLink()->title);
    }

    /** @test */
    public function it_has_a_mutable_from_date()
    {
        $this->assertEquals(new DateTime('20180201T090000 UTC'), $this->createShortEventLink()->from);
    }

    /** @test */
    public function it_has_a_mutable_to_date()
    {
        $this->assertEquals(new DateTime('20180201T180000 UTC'), $this->createShortEventLink()->to);
    }

    /** @test */
    public function it_has_an_immutable_from_date()
    {
        $this->assertEquals(new DateTime('20180201T090000 UTC'), $this->createShortEventLink()->from);
    }

    /** @test */
    public function it_has_an_immutable_to_date()
    {
        $this->assertEquals(new \DateTimeImmutable('20180201T180000 UTC'), $this->createShortEventLink()->to);
    }

    /** @test */
    public function it_can_have_a_description()
    {
        $link = $this->createShortEventLink();
        $correctDescription = 'With balloons, clowns and stuff
Bring a dog, bring a frog';
        $this->assertEquals($correctDescription, $link->description);
    }

    /** @test */
    public function it_can_have_an_address()
    {
        $link = $this->createShortEventLink();

        $this->assertEquals('Party Lane 1A, 1337 Funtown', $link->address);
    }

    /** @test */
    public function it_can_have_a_url()
    {
        $link = $this->createShortEventLink();

        $this->assertEquals('https://example.com', $link->url);
    }

    /** @test */
    public function it_can_have_a_description_with_url()
    {
        $link = $this->createShortEventLink();
        $correctDescriptionWithUrl = 'https://example.com With balloons, clowns and stuff
Bring a dog, bring a frog';

        $this->assertEquals($correctDescriptionWithUrl, $link->descriptionWithUrl);
    }

    /** @test */
    public function it_can_have_a_description_with_url_format()
    {
        $link = $this->createShortEventLink();

        $this->assertEquals('{url} {description}', $link->descriptionWithUrlFormat);
    }

    /** @test */
    public function it_can_have_a_custom_description_with_url_format()
    {
        $link = $this->createShortEventLink()->descriptionWithUrlFormat('{description} {url}');

        $this->assertEquals('{description} {url}', $link->descriptionWithUrlFormat);
    }

    /** @test */
    public function it_can_have_a_url_title()
    {
        $link = $this->createShortEventLink();

        $this->assertEquals('example.com', $link->urlTitle);
    }

    /** @test */
    public function it_can_have_a_custom_url_title()
    {
        $link = $this->createShortEventLink()->urlTitle('Example.com');

        $this->assertEquals('Example.com', $link->urlTitle);
    }
}
