<?php

declare(strict_types=1);

namespace Spatie\CalendarLinks\Tests\Generators;

use PHPUnit\Framework\Attributes\Test;
use Spatie\CalendarLinks\Generator;
use Spatie\CalendarLinks\Generators\Google;
use Spatie\CalendarLinks\Tests\TestCase;

final class GoogleGeneratorTest extends TestCase
{
    use GeneratorTestContract;

    #[\Override]
    protected function generator(): Generator
    {
        return new Google();
    }

    #[\Override]
    protected function linkMethodName(): string
    {
        return 'google';
    }

    #[Test]
    public function it_can_generate_an_url_with_custom_parameters(): void
    {
        $link = $this->createShortEventLink();

        $this->assertMatchesSnapshot($link->google(['recur' => 'RRULE:FREQ=DAILY']));
    }

    #[Test]
    public function it_can_generate_an_url_with_repeated_parameters(): void
    {
        $link = $this->createShortEventLink();

        $this->assertMatchesSnapshot($link->google([
            'sprop' => [
                'goo.allowModify:false',
                'goo.allowInvitesOther:false',
                'goo.showInvitees:false',
            ],
            'vcon' => 'meet',
        ]));
    }

    #[Test]
    public function it_omits_a_repeated_parameter_without_values(): void
    {
        $link = $this->createShortEventLink();

        $this->assertMatchesSnapshot($link->google(['sprop' => [], 'vcon' => 'meet']));
    }
}
