<?php

namespace Spatie\CalendarLinks\Tests\Generators;

use DateTime;
use DateTimeZone;
use Spatie\CalendarLinks\Generator;
use Spatie\CalendarLinks\Generators\Yahoo;
use Spatie\CalendarLinks\Link;
use Spatie\CalendarLinks\Tests\TestCase;

class YahooGeneratorTest extends TestCase
{
    use GeneratorTestContract;

    protected function generator(): Generator
    {
        return new Yahoo();
    }

    protected function linkMethodName(): string
    {
        return 'yahoo';
    }

    /** @test */
    public function it_can_generate_a_yahoo_link_for_long_multiple_days_event(): void
    {
        $link = Link::create(
            'Christmas and New Year',
            DateTime::createFromFormat('Y-m-d H:i', '2019-12-25 23:00', new DateTimeZone('UTC')),
            DateTime::createFromFormat('Y-m-d H:i', '2020-01-01 1:00', new DateTimeZone('UTC'))
        )->description('Long event')->address('Party Lane 1A, 1337 Funtown');

        $this->assertMatchesSnapshot($link->yahoo());
    }

    /** @test */
    public function it_can_generate_an_url_with_custom_parameters(): void
    {
        $link = $this->createShortEventLink();

        $this->assertMatchesSnapshot($link->yahoo(['uid' => '750e0c92aa33a7382460a280c2dfb8e6', 'msngr' => null]));
    }
}
