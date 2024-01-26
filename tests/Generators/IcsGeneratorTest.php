<?php

namespace Spatie\CalendarLinks\Tests\Generators;

use Spatie\CalendarLinks\Generator;
use Spatie\CalendarLinks\Generators\Ics;
use Spatie\CalendarLinks\Tests\TestCase;

class IcsGeneratorTest extends TestCase
{
    use GeneratorTestContract;

    /**
     * @param array $options @see \Spatie\CalendarLinks\Generators\Ics::__construct
     * @return \Spatie\CalendarLinks\Generator
     */
    protected function generator(array $options = [], array $presentationOptions = []): Generator
    {
        $presentationOptions['format'] ??= 'file';
        return new Ics($options, $presentationOptions);
    }

    protected function linkMethodName(): string
    {
        return 'ics';
    }

    /** @test */
    public function it_can_generate_an_ics_link_with_custom_uid(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator(['UID' => 'random-uid', ['format' => 'file']])->generate($this->createShortEventLink())
        );
    }

    /** @test */
    public function it_has_a_product_id(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator(['PRODID' => 'Spatie calendar-links'], ['format' => 'file'])->generate($this->createShortEventLink())
        );
    }

    /** @test */
    public function it_has_a_product_dtstamp(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator(['DTSTAMP' => '20180201T090000Z'], ['format' => 'file'])->generate($this->createShortEventLink())
        );
    }

    /** @test */
    public function it_generates_base64_encoded_link_for_html(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator([], ['format' => 'html'])->generate($this->createShortEventLink())
        );
    }
}
