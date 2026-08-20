<?php

declare(strict_types=1);

namespace Spatie\CalendarLinks\Tests;

use PHPUnit\Framework\Attributes\Test;
use Spatie\CalendarLinks\Generators\BaseOutlook;
use Spatie\CalendarLinks\Generators\Google;
use Spatie\CalendarLinks\Generators\Ics;
use Spatie\CalendarLinks\Generators\Yahoo;
use Spatie\CalendarLinks\Link;

final class GeneratorExtensibilityTest extends TestCase
{
    #[Test]
    public function ics_subclass_can_append_calendar_and_event_properties(): void
    {
        $generator = new class ([], ['format' => Ics::FORMAT_FILE]) extends Ics {
            #[\Override]
            protected function additionalCalendarProperties(Link $link): array
            {
                return ['X-WR-CALNAME:'.$this->escapeString('Spatie events')];
            }

            #[\Override]
            protected function additionalEventProperties(Link $link): array
            {
                return [
                    'CATEGORIES:'.$this->escapeString('Workshops,Parties'),
                    'STATUS:CONFIRMED',
                ];
            }
        };

        $ics = $generator->generate($this->createShortEventLink());

        $this->assertStringContainsString('X-WR-CALNAME:Spatie events', $ics);
        $this->assertStringContainsString('CATEGORIES:Workshops\,Parties', $ics);
        $this->assertStringContainsString('STATUS:CONFIRMED', $ics);

        // Calendar properties belong before BEGIN:VEVENT, event properties inside the component.
        $this->assertLessThan(strpos($ics, 'BEGIN:VEVENT'), strpos($ics, 'X-WR-CALNAME'));
        $this->assertGreaterThan(strpos($ics, 'BEGIN:VEVENT'), strpos($ics, 'CATEGORIES'));
        $this->assertLessThan(strpos($ics, 'END:VEVENT'), strpos($ics, 'STATUS:CONFIRMED'));
    }

    #[Test]
    public function google_subclass_can_change_the_base_url(): void
    {
        $generator = new class () extends Google {
            protected const string BASE_URL = 'https://calendar.google.example/render?action=TEMPLATE';
        };

        $url = $generator->generate($this->createShortEventLink());

        $this->assertStringStartsWith('https://calendar.google.example/render?action=TEMPLATE&', $url);
    }

    #[Test]
    public function yahoo_subclass_can_change_the_base_url(): void
    {
        $generator = new class () extends Yahoo {
            protected const string BASE_URL = 'https://calendar.yahoo.example/?v=60';
        };

        $url = $generator->generate($this->createShortEventLink());

        $this->assertStringStartsWith('https://calendar.yahoo.example/?v=60&', $url);
    }

    #[Test]
    public function yahoo_subclass_can_reuse_the_text_sanitizer(): void
    {
        $generator = new class () extends Yahoo {
            #[\Override]
            public function generate(Link $link): string
            {
                return parent::generate($link).'&REM1='.$this->sanitizeText('30 minutes before');
            }
        };

        $url = $generator->generate($this->createShortEventLink());

        $this->assertStringContainsString('&REM1=30%20minutes%20before', $url);
    }

    #[Test]
    public function outlook_subclass_can_reuse_the_string_sanitizer(): void
    {
        $generator = new class () extends BaseOutlook {
            #[\Override]
            protected function baseUrl(): string
            {
                return 'https://outlook.example/compose?rru=addevent';
            }

            #[\Override]
            public function generate(Link $link): string
            {
                return parent::generate($link).'&category='.$this->sanitizeString('Fun & Games');
            }
        };

        $url = $generator->generate($this->createShortEventLink());

        $this->assertStringStartsWith('https://outlook.example/compose?rru=addevent&', $url);
        $this->assertStringContainsString('&category=Fun%20%26%20Games', $url);
    }
}
