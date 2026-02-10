<?php

declare(strict_types=1);

namespace Spatie\CalendarLinks\Tests\Generators;

use Spatie\CalendarLinks\Generator;
use Spatie\CalendarLinks\Generators\WebOffice;
use Spatie\CalendarLinks\Tests\TestCase;

final class WebOfficeGeneratorTest extends TestCase
{
    use GeneratorTestContract;

    #[\Override]
    protected function generator(): Generator
    {
        return new WebOffice();
    }

    #[\Override]
    protected function linkMethodName(): string
    {
        return 'webOffice';
    }

    /** @test */
    public function it_can_generate_an_url_with_custom_parameters(): void
    {
        $link = $this->createShortEventLink();

        $this->assertMatchesSnapshot($link->webOffice(['online' => 1]));
    }
}
