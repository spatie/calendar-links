<?php

namespace Spatie\CalendarLink\Test;

use Spatie\CalendarLinks\Generator;
use Spatie\CalendarLinks\Generators\Ics;
use Spatie\CalendarLinks\Test\Generators\GeneratorTestContract;
use Spatie\CalendarLinks\Test\TestCase;

class IcsGeneratorTest extends TestCase
{
    use GeneratorTestContract;

    protected function generator(): Generator
    {
        return new Ics();
    }

    protected function linkMethodName(): string
    {
        return 'ics';
    }

    /** @test */
    public function it_can_generate_an_ics_link_with_custom_uid()
    {
        $this->assertMatchesSnapshot(
            $this->createShortEventLink()->ics(['uid' => 'random-uid'])
        );
    }
}
