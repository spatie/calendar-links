<?php

declare(strict_types=1);

namespace Spatie\CalendarLinks\Tests\Generators;

use PHPUnit\Framework\Attributes\Test;
use Spatie\CalendarLinks\Generator;
use Spatie\CalendarLinks\Generators\WebOutlook;
use Spatie\CalendarLinks\Tests\TestCase;

final class WebOutlookGeneratorTest extends TestCase
{
    use GeneratorTestContract;

    #[\Override]
    protected function generator(): Generator
    {
        return new WebOutlook();
    }

    #[\Override]
    protected function linkMethodName(): string
    {
        return 'webOutlook';
    }

    #[Test]
    public function it_can_generate_an_url_with_custom_parameters(): void
    {
        $link = $this->createShortEventLink();

        $this->assertMatchesSnapshot($link->webOutlook(['online' => 1]));
    }
}
