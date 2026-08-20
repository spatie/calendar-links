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

    #[Test]
    public function it_can_generate_an_url_with_separate_start_and_end_timezones(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator()->generate($this->createFlightWithDistinctTimezonesLink())
        );
    }

    #[Test]
    public function it_does_not_emit_ctz_alongside_stz_and_etz(): void
    {
        // stz takes priority over ctz, so emitting both would only invite confusion.
        $url = $this->generator()->generate($this->createFlightWithDistinctTimezonesLink());

        $this->assertStringContainsString('&stz=Asia/Tokyo&etz=America/Los_Angeles', $url);
        $this->assertStringNotContainsString('ctz=', $url);
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

    #[Test]
    public function it_keeps_a_description_and_address_of_zero(): void
    {
        // '0' is falsy in PHP, so only an empty string may drop these fields.
        $link = $this->createShortEventLink()->description('0')->address('0');

        $url = $this->generator()->generate($link);

        $this->assertStringContainsString('&details=0', $url);
        $this->assertStringContainsString('&location=0', $url);
    }
}
