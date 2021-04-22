<?php

namespace Spatie\CalendarLinks\Tests\Generators;

use Spatie\CalendarLinks\Generator;
use Spatie\CalendarLinks\Generators\Ics;
use Spatie\CalendarLinks\Tests\TestCase;

class IcsGeneratorTest extends TestCase
{
    use GeneratorTestContract;

    protected function generator(): Generator
    {
        // extend base class just to make output more readable and simplify reviewing of the snapshot diff
        return new class extends Ics {
            protected function buildLink(array $propertiesAndComponents): string
            {
                return implode("\r\n", $propertiesAndComponents);
            }
        };
    }

    protected function linkMethodName(): string
    {
        return 'ics';
    }

    /** @test */
    public function it_can_generate_an_ics_link_with_custom_uid()
    {
        $this->assertMatchesSnapshot(
            $this->createShortEventLink()->ics(['UID' => 'random-uid'])
        );
    }
}
