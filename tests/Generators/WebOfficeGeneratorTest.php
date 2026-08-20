<?php

declare(strict_types=1);

namespace Spatie\CalendarLinks\Tests\Generators;

use PHPUnit\Framework\Attributes\Test;
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

    #[Test]
    public function it_can_generate_an_url_with_custom_parameters(): void
    {
        $link = $this->createShortEventLink();

        $this->assertMatchesSnapshot($link->webOffice(['online' => 1]));
    }

    #[Test]
    public function it_can_generate_an_url_with_repeated_parameters(): void
    {
        $link = $this->createShortEventLink();

        $this->assertMatchesSnapshot($link->webOffice(['to' => ['first@example.com', 'second@example.com'], 'online' => 1]));
    }
}
