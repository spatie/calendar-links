<?php

declare(strict_types=1);

namespace Spatie\CalendarLinks\Tests\Generators;

use DateTime;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use Spatie\CalendarLinks\Exceptions\InvalidLink;
use Spatie\CalendarLinks\Generator;
use Spatie\CalendarLinks\Generators\Ics;
use Spatie\CalendarLinks\Link;
use Spatie\CalendarLinks\Tests\TestCase;

/**
 * @psalm-import-type IcsOptions from \Spatie\CalendarLinks\Generators\Ics
 * @psalm-import-type IcsPresentationOptions from \Spatie\CalendarLinks\Generators\Ics
 */
final class IcsGeneratorTest extends TestCase
{
    use GeneratorTestContract;

    /**
     * @psalm-param IcsOptions $options ICS-specific properties and components
     * @param IcsOptions $options ICS-specific properties and components
     * @param IcsPresentationOptions $presentationOptions
     * @return \Spatie\CalendarLinks\Generator
     */
    #[\Override]
    protected function generator(array $options = [], array $presentationOptions = []): Generator
    {
        $presentationOptions['format'] ??= Ics::FORMAT_FILE;

        return new Ics($options, $presentationOptions);
    }

    #[Test]
    public function it_correctly_generates_all_day_events_by_days(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator()->generate($this->createAllDayEventMultipleDaysWithTimezoneLink())
        );
    }

    #[Test]
    public function it_correctly_generates_all_day_events_by_dates(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator()->generate($this->createEventMultipleDaysViaStartEndWithTimezoneLink())
        );
    }

    #[Test]
    public function it_generates_base64_encoded_link_for_html(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator([], ['format' => Ics::FORMAT_HTML])->generate($this->createShortEventLink())
        );
    }

    #[Test]
    public function it_can_generate_an_ics_link_with_custom_uid(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator(['UID' => 'random-uid'])->generate($this->createShortEventLink())
        );
    }

    #[Test]
    public function it_supports_custom_product_id(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator(['PRODID' => 'My Product'])->generate($this->createShortEventLink())
        );
    }

    #[Test]
    public function it_can_generate_with_a_default_reminder(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator(['REMINDER' => []])->generate($this->createShortEventLink())
        );
    }

    #[Test]
    public function it_can_generate_with_a_custom_reminder(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator(['REMINDER' => [
                'DESCRIPTION' => 'Party with balloons and cake!',
                'TIME' => DateTime::createFromFormat('Y-m-d H:i', '2018-02-01 08:15', new DateTimeZone('UTC')),
            ]])->generate($this->createShortEventLink())
        );
    }

    #[Test]
    public function it_can_generate_an_event_with_separate_start_and_end_timezones(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator()->generate($this->createFlightWithDistinctTimezonesLink())
        );
    }

    #[Test]
    public function it_keeps_dtstamp_in_utc_next_to_zoned_endpoints(): void
    {
        // DTSTAMP must always be UTC, whatever the event's own zones are.
        $output = $this->generator()->generate($this->createFlightWithDistinctTimezonesLink());

        $this->assertStringContainsString('DTSTAMP:20270315T000000Z', $output);
        $this->assertStringContainsString('DTSTART;TZID=Asia/Tokyo:20270315T090000', $output);
        $this->assertStringContainsString('DTEND;TZID=America/Los_Angeles:20270315T093000', $output);
    }

    #[Test]
    public function it_stamps_a_timed_event_with_its_start_by_default(): void
    {
        // The default DTSTAMP is the event start, so the same Link always produces the same file.
        $output = $this->generator()->generate($this->createShortEventLink());

        $this->assertStringContainsString('DTSTAMP:20180201T090000Z', $output);
    }

    #[Test]
    public function it_stamps_an_all_day_event_with_a_date_time_rather_than_a_date(): void
    {
        // DTSTART drops to a bare DATE for an all-day event, but DTSTAMP may never do the same.
        $output = $this->generator()->generate($this->createSingleDayAllDayEventLink());

        $this->assertStringContainsString('DTSTAMP:20180201T000000Z', $output);
        $this->assertStringContainsString('DTSTART;VALUE=DATE:20180201', $output);
    }

    #[Test]
    public function it_can_generate_an_event_with_a_custom_dtstamp(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator([
                'DTSTAMP' => new \DateTimeImmutable('2018-01-15 11:30', new DateTimeZone('UTC')),
            ])->generate($this->createShortEventLink())
        );
    }

    #[Test]
    public function it_converts_a_custom_dtstamp_to_utc(): void
    {
        // A DTSTAMP given in any zone is written as the same instant in UTC: 11:30 in Tokyo is 02:30 UTC.
        $output = $this->generator([
            'DTSTAMP' => new \DateTimeImmutable('2018-01-15 11:30', new DateTimeZone('Asia/Tokyo')),
        ])->generate($this->createShortEventLink());

        $this->assertStringContainsString('DTSTAMP:20180115T023000Z', $output);
    }

    #[Test]
    public function it_stamps_an_all_day_event_with_a_custom_dtstamp_as_a_date_time(): void
    {
        $output = $this->generator([
            'DTSTAMP' => new \DateTimeImmutable('2018-01-15 11:30', new DateTimeZone('UTC')),
        ])->generate($this->createSingleDayAllDayEventLink());

        $this->assertStringContainsString('DTSTAMP:20180115T113000Z', $output);
    }

    #[Test]
    public function it_rejects_a_dtstamp_that_is_not_a_date_time(): void
    {
        // Falling back to the default would hand the caller a file that looks right and carries the
        // wrong stamp, so a wrong type is refused outright, and refused before any output is built.
        $this->expectException(InvalidLink::class);
        $this->expectExceptionMessage('The `DTSTAMP` option must be a DateTimeInterface, `string` given.');

        /** @psalm-suppress InvalidArgument */
        $this->generator(['DTSTAMP' => '2026-01-01']);
    }

    #[Test]
    public function it_rejects_a_dtstamp_that_is_not_a_date_time_through_the_link(): void
    {
        $link = $this->createShortEventLink();

        $this->expectException(InvalidLink::class);

        /** @psalm-suppress InvalidArgument */
        $link->ics(['DTSTAMP' => '2026-01-01'], ['format' => Ics::FORMAT_FILE]);
    }

    #[Test]
    public function it_can_generate_a_recurring_event(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator(['RRULE' => 'FREQ=WEEKLY;BYDAY=MO,WE,FR;COUNT=10'])->generate($this->createShortEventLink())
        );
    }

    #[Test]
    public function it_keeps_the_separators_of_a_recurrence_rule_unescaped(): void
    {
        // A RECUR value is structured, so escaping its semicolons and commas would break the rule.
        $output = $this->generator(['RRULE' => 'FREQ=WEEKLY;BYDAY=MO,WE,FR'])->generate($this->createShortEventLink());

        $this->assertStringContainsString('RRULE:FREQ=WEEKLY;BYDAY=MO,WE,FR', $output);
    }

    #[Test]
    public function it_can_generate_an_event_with_availability_and_visibility(): void
    {
        $this->assertMatchesSnapshot(
            $this->generator([
                'TRANSP' => 'TRANSPARENT',
                'CLASS' => 'PRIVATE',
                'X-MICROSOFT-CDO-BUSYSTATUS' => 'OOF',
            ])->generate($this->createShortEventLink())
        );
    }

    #[Test]
    public function it_percent_encodes_uri_significant_characters_in_attendee_addresses(): void
    {
        // `?`, `&`, `=`, `/` and `%` are all legal in an email address, and all significant in a mailto URI.
        $link = $this->createShortEventLink()->guest('who?what&why=how/now%then@example.com');

        $output = $this->generator()->generate($link);

        $this->assertStringContainsString(
            'ATTENDEE;ROLE=REQ-PARTICIPANT:mailto:who%3Fwhat%26why%3Dhow%2Fnow%25then@example.com',
            $this->unfold($output)
        );
    }

    #[Test]
    public function it_folds_content_lines_longer_than_75_octets(): void
    {
        $title = str_repeat('a', 200);

        $output = $this->generator()->generate($this->eventWithTitle($title));

        foreach (explode("\r\n", $output) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line), "Line exceeds 75 octets: {$line}");
        }

        $this->assertStringContainsString("SUMMARY:{$title}", $this->unfold($output));
        // The fold is CRLF plus exactly one space, and the space counts towards the 75 octets.
        $this->assertStringContainsString('SUMMARY:'.str_repeat('a', 67)."\r\n ".str_repeat('a', 74), $output);
    }

    #[Test]
    public function it_does_not_split_a_multibyte_character_when_folding(): void
    {
        // Padding of 67 octets fills the first line up to the fold boundary, so the next character
        // to be written is the first emoji and its four octets straddle the limit.
        $title = str_repeat('a', 67).str_repeat('🎉', 20);

        $output = $this->generator()->generate($this->eventWithTitle($title));

        foreach (explode("\r\n", $output) as $line) {
            $this->assertTrue(mb_check_encoding($line, 'UTF-8'), "Line is not valid UTF-8: {$line}");
            $this->assertLessThanOrEqual(75, strlen($line), "Line exceeds 75 octets: {$line}");
        }

        $this->assertStringContainsString("SUMMARY:{$title}", $this->unfold($output));
    }

    #[Test]
    public function it_folds_a_line_that_unfolding_restores_exactly(): void
    {
        // Two byte Cyrillic and four byte emoji land on different boundaries, so both paths of the
        // backtracking are exercised, and unfolding has to give back the input byte for byte.
        $title = 'Годовщина 🎉 '.str_repeat('праздник ', 12).str_repeat('🎂', 15);

        $output = $this->generator()->generate($this->eventWithTitle($title));

        $this->assertStringContainsString('SUMMARY:'.$title."\r\n", $this->unfold($output));
    }

    #[Test]
    public function it_terminates_the_output_with_a_crlf(): void
    {
        $output = $this->generator()->generate($this->createShortEventLink());

        $this->assertStringEndsWith("END:VCALENDAR\r\n", $output);
    }

    #[Test]
    public function it_declares_the_registered_charset_name_in_the_data_uri(): void
    {
        $output = $this->generator([], ['format' => Ics::FORMAT_HTML])->generate($this->createShortEventLink());

        $this->assertStringStartsWith('data:text/calendar;charset=utf-8;base64,', $output);
    }

    #[Test]
    public function it_folds_the_base64_encoded_link_as_well(): void
    {
        $title = str_repeat('a', 200);

        $output = $this->generator([], ['format' => Ics::FORMAT_HTML])->generate($this->eventWithTitle($title));

        $decoded = base64_decode(substr($output, strlen('data:text/calendar;charset=utf-8;base64,')), true);

        $this->assertIsString($decoded);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $decoded);
        $this->assertStringContainsString("SUMMARY:{$title}", $this->unfold($decoded));
        $this->assertStringContainsString("\r\n ", $decoded);
    }

    #[Test]
    public function it_strips_control_characters_that_are_invalid_in_a_text_value(): void
    {
        $link = $this->eventWithTitle("Bell\x07 and null\x00 and delete\x7F")
            ->description("Vertical\x0Btab and unit\x1Fseparator")
            ->address("Tab\tkept, comma; semicolon\\ backslash\nnewline");

        $output = $this->generator()->generate($link);

        $this->assertStringContainsString('SUMMARY:Bell and null and delete', $output);
        $this->assertStringContainsString('DESCRIPTION:Verticaltab and unitseparator', $output);
        // HTAB stays, and the existing TEXT escaping is untouched by the stripping.
        $this->assertStringContainsString("LOCATION:Tab\tkept\\, comma\\; semicolon\\\\ backslash\\nnewline", $output);
    }

    /** @test */
    public function it_escapes_backslashes_in_text_fields(): void
    {
        $link = Link::create(
            'Event with \\ backslash',
            DateTime::createFromFormat('Y-m-d H:i', '2024-01-01 09:00', new DateTimeZone('UTC')),
            DateTime::createFromFormat('Y-m-d H:i', '2024-01-01 10:00', new DateTimeZone('UTC')),
        )->description('Path: C:\\Users\\test');

        $output = $this->generator()->generate($link);

        $this->assertStringContainsString('SUMMARY:Event with \\\\ backslash', $output);
        $this->assertStringContainsString('DESCRIPTION:Path: C:\\\\Users\\\\test', $output);
    }

    /** @test */
    public function it_escapes_newlines_as_backslash_n(): void
    {
        $link = Link::create(
            'Event',
            DateTime::createFromFormat('Y-m-d H:i', '2024-01-01 09:00', new DateTimeZone('UTC')),
            DateTime::createFromFormat('Y-m-d H:i', '2024-01-01 10:00', new DateTimeZone('UTC')),
        )->description("Line 1\r\nLine 2\rLine 3\nLine 4");

        $output = $this->generator()->generate($link);

        $this->assertStringContainsString('DESCRIPTION:Line 1\\nLine 2\\nLine 3\\nLine 4', $output);
    }

    #[Test]
    public function it_keeps_a_description_and_address_of_zero(): void
    {
        // '0' is falsy in PHP, so only an empty string may drop these fields.
        $link = $this->createShortEventLink()->description('0')->address('0');

        $output = $this->generator()->generate($link);

        $this->assertStringContainsString('DESCRIPTION:0', $output);
        $this->assertStringContainsString('LOCATION:0', $output);
    }

    private function eventWithTitle(string $title): Link
    {
        return Link::create(
            $title,
            DateTime::createFromFormat('Y-m-d H:i', '2018-02-01 09:00', new DateTimeZone('UTC')),
            DateTime::createFromFormat('Y-m-d H:i', '2018-02-01 18:00', new DateTimeZone('UTC'))
        );
    }

    /** Reverses the RFC 5545 §3.1 folding: a CRLF followed by a single space is removed. */
    private function unfold(string $output): string
    {
        return str_replace("\r\n ", '', $output);
    }
}
