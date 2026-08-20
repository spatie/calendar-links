<?php

declare(strict_types=1);

namespace Spatie\CalendarLinks\Tests\Generators;

use DateTime;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
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

    /** @param non-empty-string $timezone */
    #[Test]
    #[TestWith(['+02:00', '20260101T080000Z', '20260101T090000Z'])]
    #[TestWith(['-05:00', '20260101T150000Z', '20260101T160000Z'])]
    #[TestWith(['Etc/GMT+5', '20260101T150000Z', '20260101T160000Z'])]
    #[TestWith(['CEST', '20260101T080000Z', '20260101T090000Z'])]
    public function it_writes_utc_endpoints_for_a_timezone_that_cannot_be_named(string $timezone, string $start, string $end): void
    {
        $output = $this->generator()->generate($this->createEventLinkInTimezone($timezone));

        $this->assertStringContainsString('DTSTART:'.$start, $output);
        $this->assertStringContainsString('DTEND:'.$end, $output);
        $this->assertStringNotContainsString('TZID', $output);
    }

    #[Test]
    public function it_falls_back_to_utc_when_only_one_end_of_a_flight_names_a_zone(): void
    {
        // An unquoted `:` inside a param-value would cut `DTSTART;TZID=+02:00:20270315T090000` in
        // half, and naming only the departure would leave the pair inconsistent anyway.
        $output = $this->generator()->generate($this->createFlightWithUnresolvableEndTimezoneLink());

        $this->assertStringContainsString('DTSTART:20270315T000000Z', $output);
        $this->assertStringContainsString('DTEND:20270315T163000Z', $output);
        $this->assertStringNotContainsString('TZID', $output);
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
    public function it_emits_plain_utc_endpoints_for_two_spellings_of_utc(): void
    {
        $output = $this->generator()->generate($this->createEventAcrossUtcAliasesLink());

        $this->assertStringContainsString('DTSTART:20260101T100000Z', $output);
        $this->assertStringContainsString('DTEND:20260101T110000Z', $output);
        $this->assertStringNotContainsString('TZID=', $output);
    }

    #[Test]
    public function it_emits_plain_utc_endpoints_for_a_z_suffixed_start(): void
    {
        // `Z` is not a TZID any calendar can resolve, so it must never reach the output.
        $output = $this->generator()->generate($this->createEventWithZSuffixedStartLink());

        $this->assertStringContainsString('DTSTART:20260101T100000Z', $output);
        $this->assertStringContainsString('DTEND:20260101T110000Z', $output);
        $this->assertStringNotContainsString('TZID=', $output);
    }

    #[Test]
    public function it_still_names_both_zones_when_two_places_share_an_offset(): void
    {
        $output = $this->generator()->generate($this->createFlightBetweenPlacesSharingAnOffsetLink());

        $this->assertStringContainsString('DTSTART;TZID=Europe/London:20260115T090000', $output);
        $this->assertStringContainsString('DTEND;TZID=Europe/Lisbon:20260115T114500', $output);
    }

    #[Test]
    public function it_still_names_both_zones_when_the_start_uses_a_legacy_alias(): void
    {
        $output = $this->generator()->generate($this->createFlightBookedWithALegacyZoneAliasLink());

        $this->assertStringContainsString('DTSTART;TZID=Japan:20260115T090000', $output);
        $this->assertStringContainsString('DTEND;TZID=Asia/Seoul:20260115T114500', $output);
    }

    #[Test]
    public function it_defines_a_vtimezone_for_every_referenced_tzid(): void
    {
        // @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.6.5
        $output = $this->generator()->generate($this->createFlightWithDistinctTimezonesLink());

        $this->assertSame(2, substr_count($output, 'BEGIN:VTIMEZONE'));
        $this->assertStringContainsString("BEGIN:VTIMEZONE\r\nTZID:Asia/Tokyo\r\n", $output);
        $this->assertStringContainsString("BEGIN:VTIMEZONE\r\nTZID:America/Los_Angeles\r\n", $output);

        // A component is only useful to a client that reads it before the event that leans on it.
        $this->assertLessThan(strpos($output, 'BEGIN:VEVENT'), strpos($output, 'BEGIN:VTIMEZONE'));
        $this->assertLessThan(strpos($output, 'BEGIN:VEVENT'), strpos($output, 'END:VTIMEZONE'));
    }

    #[Test]
    public function it_describes_both_observances_of_a_zone_that_changes_its_clocks(): void
    {
        // The flight lands on 2027-03-15, the day after Los Angeles moved to daylight saving time,
        // so the change that applies to it is the last one written before it.
        $output = $this->generator()->generate($this->createFlightWithDistinctTimezonesLink());

        $this->assertStringContainsString(
            implode("\r\n", [
                'BEGIN:STANDARD',
                'DTSTART:20261101T020000',
                'TZOFFSETFROM:-0700',
                'TZOFFSETTO:-0800',
                'TZNAME:PST',
                'END:STANDARD',
                'BEGIN:DAYLIGHT',
                'DTSTART:20270314T020000',
                'TZOFFSETFROM:-0800',
                'TZOFFSETTO:-0700',
                'TZNAME:PDT',
                'END:DAYLIGHT',
            ]),
            $output
        );
    }

    #[Test]
    public function it_describes_a_zone_that_keeps_one_offset_with_a_standard_observance_alone(): void
    {
        // Neither zone observes daylight saving time, so there is no DAYLIGHT rule to state.
        $link = Link::create(
            'Bengaluru to Reykjavik',
            new DateTime('2027-07-01 09:00', new DateTimeZone('Asia/Kolkata')),
            new DateTime('2027-07-01 15:30', new DateTimeZone('Atlantic/Reykjavik')),
        );

        $output = $this->generator()->generate($link);

        $this->assertSame(2, substr_count($output, 'BEGIN:VTIMEZONE'));
        $this->assertSame(2, substr_count($output, 'BEGIN:STANDARD'));
        $this->assertStringNotContainsString('BEGIN:DAYLIGHT', $output);

        // A zone that never changes leaves and arrives at the same offset.
        $this->assertStringContainsString("TZOFFSETFROM:+0530\r\nTZOFFSETTO:+0530\r\nTZNAME:IST", $output);
        $this->assertStringContainsString("TZOFFSETFROM:+0000\r\nTZOFFSETTO:+0000\r\nTZNAME:GMT", $output);
    }

    #[Test]
    public function it_keeps_every_change_of_a_zone_that_changes_twice_in_one_direction(): void
    {
        // Morocco suspends its summer time for Ramadan and restores it weeks later, so a single year
        // holds two changes to standard time at two different offsets. Keeping one observance of
        // each kind would drop the March restoration and leave the event resolving to the Ramadan
        // offset, an hour out.
        $link = Link::create(
            'Casablanca to Tokyo',
            new DateTime('2026-05-15 09:00', new DateTimeZone('Africa/Casablanca')),
            new DateTime('2026-05-15 20:00', new DateTimeZone('Asia/Tokyo')),
        );

        $output = $this->generator()->generate($link);
        $onsets = $this->onsetsOf('Africa/Casablanca', $output);

        $this->assertSame([
            ['20250515T090000', '+0100'], // the offset already in effect when the window opens
            ['20260215T030000', '+0000'], // Ramadan begins, the clocks go back
            ['20260322T020000', '+0100'], // Ramadan ends, summer time is restored
            ['20260920T020000', '+0000'], // the ordinary end of summer time
        ], $onsets);

        // A local time takes the observance with the latest onset at or before it. Of the three that
        // precede the event starting 2026-05-15 09:00, the restoration is the last, so the event
        // resolves to +0100 rather than the Ramadan offset before it.
        $this->assertSame([
            ['20250515T090000', '+0100'],
            ['20260215T030000', '+0000'],
            ['20260322T020000', '+0100'],
        ], array_values(array_filter(
            $onsets,
            static fn (array $onset): bool => $onset[0] <= '20260515T090000'
        )));
    }

    #[Test]
    public function it_defines_a_zone_that_has_no_transition_table(): void
    {
        // A zone named by a bare abbreviation has no transitions for PHP to return, and reading them
        // unguarded warned and left a VTIMEZONE with no subcomponent at all, which RFC 5545 forbids.
        // Such a zone no longer reaches this code through an endpoint, since it cannot be named by a
        // TZID either, but referencedTimezones() is open for a subclass to put one here.
        $generator = new class ([], ['format' => Ics::FORMAT_FILE]) extends Ics {
            #[\Override]
            protected function referencedTimezones(Link $link): array
            {
                return [new DateTimeZone('EST')];
            }
        };

        set_error_handler(static function (int $severity, string $message): never {
            throw new \ErrorException($message, severity: $severity);
        });

        try {
            $output = $generator->generate($this->createFlightWithDistinctTimezonesLink());
        } finally {
            restore_error_handler();
        }

        $this->assertSame([['20260314T190000', '-0500']], $this->onsetsOf('EST', $output));

        // A zone that never changes leaves and arrives at the same offset.
        $this->assertStringContainsString("TZOFFSETFROM:-0500\r\nTZOFFSETTO:-0500\r\nTZNAME:EST", $output);
    }

    /**
     * The onsets of one zone's component, as [DTSTART, TZOFFSETTO] pairs in the order written.
     *
     * @return list<array{string, string}>
     */
    private function onsetsOf(string $tzid, string $ics): array
    {
        preg_match('/BEGIN:VTIMEZONE\r\nTZID:'.preg_quote($tzid, '/').'\r\n(.*?)END:VTIMEZONE/s', $ics, $component);
        preg_match_all('/DTSTART:(\d{8}T\d{6})\r\n.*?TZOFFSETTO:(\S+)/s', $component[1] ?? '', $onsets, PREG_SET_ORDER);

        return array_map(static fn (array $onset): array => [$onset[1], $onset[2]], $onsets);
    }

    #[Test]
    public function it_defines_a_zone_referenced_twice_only_once(): void
    {
        // The rule is one component per unique TZID, not one per property that names a zone.
        $generator = new class ([], ['format' => Ics::FORMAT_FILE]) extends Ics {
            #[\Override]
            protected function referencedTimezones(Link $link): array
            {
                return [new DateTimeZone('Asia/Tokyo'), new DateTimeZone('Asia/Tokyo')];
            }
        };

        $output = $generator->generate($this->createFlightWithDistinctTimezonesLink());

        $this->assertSame(1, substr_count($output, 'BEGIN:VTIMEZONE'));
        $this->assertSame(1, substr_count($output, 'TZID:Asia/Tokyo'));
    }

    #[Test]
    public function it_defines_no_timezone_when_the_gate_is_closed(): void
    {
        // The components and the TZID parameters that reference them are gated together, so a
        // narrower gate must leave no component behind naming a zone the file never mentions.
        $generator = new class ([], ['format' => Ics::FORMAT_FILE]) extends Ics {
            #[\Override]
            protected function shouldDefineTimezones(Link $link): bool
            {
                return false;
            }
        };

        $output = $generator->generate($this->createFlightWithDistinctTimezonesLink());

        $this->assertStringNotContainsString('VTIMEZONE', $output);
    }

    #[Test]
    public function it_defines_no_timezone_for_a_zone_the_client_cannot_resolve(): void
    {
        // An offset style name cannot go in a TZID, so both endpoints fall back to UTC instants and
        // reference no zone. Defining one anyway would leave an orphan component, and would write
        // the `-07:00` into a TZID whose value cannot carry the colon.
        $link = Link::create(
            'Tokyo to nowhere in particular',
            new DateTime('2027-03-15 09:00', new DateTimeZone('Asia/Tokyo')),
            new DateTime('2027-03-15 09:30', new DateTimeZone('-07:00')),
        );

        $output = $this->generator()->generate($link);

        $this->assertStringNotContainsString('VTIMEZONE', $output);
        $this->assertStringNotContainsString('TZID', $output);
        $this->assertStringContainsString('DTSTART:20270315T000000Z', $output);
    }

    #[Test]
    public function it_defines_no_timezone_when_the_endpoints_share_a_zone(): void
    {
        // Both endpoints are written as UTC instants, so no TZID is referenced and nothing needs defining.
        $output = $this->generator()->generate($this->createShortEventLink());

        $this->assertStringNotContainsString('VTIMEZONE', $output);
        $this->assertStringContainsString('DTSTART:20180201T090000Z', $output);
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

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function lineBreakInjectionProvider(): \Generator
    {
        foreach (['URL', 'RRULE'] as $property) {
            yield "{$property} with CRLF" => [$property, "x\r\nORGANIZER:mailto:attacker@example.com"];
            yield "{$property} with a bare LF" => [$property, "x\nORGANIZER:mailto:attacker@example.com"];
            yield "{$property} with a bare CR" => [$property, "x\rORGANIZER:mailto:attacker@example.com"];
        }
    }

    #[Test]
    #[DataProvider('lineBreakInjectionProvider')]
    public function it_rejects_a_line_break_in_a_property_it_cannot_escape(string $property, string $value): void
    {
        $this->expectException(InvalidLink::class);
        $this->expectExceptionMessage("ICS property (`{$property}`) must not contain a CR or an LF character.");

        /** @psalm-suppress ArgumentTypeCoercion We are deliberately passing a value the type forbids. */
        $this->generator([$property => $value]);
    }

    /**
     * @return \Generator<string, array{string, string, string}>
     */
    public static function unsupportedTokenProvider(): \Generator
    {
        yield 'TRANSP' => ['TRANSP', 'BUSY', '`OPAQUE`, `TRANSPARENT`'];
        yield 'CLASS' => ['CLASS', 'SECRET', '`PUBLIC`, `PRIVATE`, `CONFIDENTIAL`'];
        yield 'X-MICROSOFT-CDO-BUSYSTATUS' => ['X-MICROSOFT-CDO-BUSYSTATUS', 'AWAY', '`FREE`, `TENTATIVE`, `BUSY`, `OOF`'];
    }

    #[Test]
    #[DataProvider('unsupportedTokenProvider')]
    public function it_rejects_a_token_outside_the_allowed_list(string $property, string $value, string $allowed): void
    {
        $this->expectException(InvalidLink::class);
        $this->expectExceptionMessage("ICS property (`{$property}`) value (`{$value}`) is invalid. Pass one of {$allowed}.");

        /** @psalm-suppress ArgumentTypeCoercion We are deliberately passing a value the type forbids. */
        $this->generator([$property => $value]);
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function allowedTokenProvider(): \Generator
    {
        foreach (['OPAQUE', 'TRANSPARENT'] as $token) {
            yield "TRANSP {$token}" => ['TRANSP', $token];
        }
        foreach (['PUBLIC', 'PRIVATE', 'CONFIDENTIAL'] as $token) {
            yield "CLASS {$token}" => ['CLASS', $token];
        }
        foreach (['FREE', 'TENTATIVE', 'BUSY', 'OOF'] as $token) {
            yield "X-MICROSOFT-CDO-BUSYSTATUS {$token}" => ['X-MICROSOFT-CDO-BUSYSTATUS', $token];
        }
    }

    #[Test]
    #[DataProvider('allowedTokenProvider')]
    public function it_accepts_every_token_of_an_enumerated_property(string $property, string $token): void
    {
        /** @psalm-suppress ArgumentTypeCoercion The property and token pair is valid, but only known at runtime. */
        $output = $this->generator([$property => $token])->generate($this->createShortEventLink());

        $this->assertStringContainsString("{$property}:{$token}", $output);
    }

    #[Test]
    public function it_keeps_accepting_a_uid_a_prodid_a_url_and_a_rrule_without_line_breaks(): void
    {
        $output = $this->generator([
            'UID' => 'random-uid',
            'PRODID' => 'My Product',
            'URL' => 'https://example.com/event?id=1',
            'RRULE' => 'FREQ=WEEKLY;BYDAY=MO,WE,FR',
        ])->generate($this->createShortEventLink());

        $this->assertStringContainsString('UID:random-uid', $output);
        $this->assertStringContainsString('PRODID:My Product', $output);
        $this->assertStringContainsString('URL;VALUE=URI:https://example.com/event?id=1', $output);
        $this->assertStringContainsString('RRULE:FREQ=WEEKLY;BYDAY=MO,WE,FR', $output);
    }

    #[Test]
    public function it_escapes_a_custom_reminder_description(): void
    {
        // A VALARM DESCRIPTION is TEXT, so its separators and line breaks need the same escaping as any other.
        $output = $this->generator(['REMINDER' => [
            'DESCRIPTION' => "Bring: cake, balloons; and a C:\\Users\\map\nSee you there",
        ]])->generate($this->createShortEventLink());

        $this->assertStringContainsString(
            'DESCRIPTION:Bring: cake\, balloons\; and a C:\\\\Users\\\\map\\nSee you there',
            $output
        );
        $this->assertStringNotContainsString("\r\nSee you there", $output);
    }

    #[Test]
    public function it_rejects_a_line_break_carried_by_a_stringable_property(): void
    {
        // generate() builds its content lines by concatenation, so an object is stringified on the way
        // into the file. Checking the value as passed rather than as written would miss this entirely.
        $rrule = new class () {
            public function __toString(): string
            {
                return "FREQ=WEEKLY\r\nORGANIZER:mailto:attacker@example.com";
            }
        };

        $this->expectException(InvalidLink::class);
        $this->expectExceptionMessage('ICS property (`RRULE`) must not contain a CR or an LF character.');

        /** @psalm-suppress ImplicitToStringCast We are deliberately passing an object where the type says string. */
        $this->generator(['RRULE' => $rrule]);
    }

    #[Test]
    public function it_rejects_a_stringable_token_outside_the_allowed_list(): void
    {
        $transp = new class () {
            public function __toString(): string
            {
                return 'bogus';
            }
        };

        $this->expectException(InvalidLink::class);
        $this->expectExceptionMessage('ICS property (`TRANSP`) value (`bogus`) is invalid. Pass one of `OPAQUE`, `TRANSPARENT`.');

        /** @psalm-suppress ImplicitToStringCast We are deliberately passing an object where the type says string. */
        $this->generator(['TRANSP' => $transp]);
    }

    #[Test]
    public function it_writes_the_string_it_checked_rather_than_asking_a_stringable_twice(): void
    {
        // An object is free to answer differently on a second call, so checking one string and writing
        // another would leave the same hole open. The checked string is the one that reaches the file.
        $uid = new class () {
            private int $calls = 0;

            public function __toString(): string
            {
                return $this->calls++ === 0 ? 'first-answer' : "second\r\nORGANIZER:mailto:attacker@example.com";
            }
        };

        /** @psalm-suppress ImplicitToStringCast We are deliberately passing an object where the type says string. */
        $output = $this->generator(['UID' => $uid])->generate($this->createShortEventLink());

        $this->assertStringContainsString('UID:first-answer', $output);
        $this->assertStringNotContainsString('ORGANIZER', $output);
    }

    #[Test]
    public function it_treats_a_null_option_as_absent_rather_than_invalid(): void
    {
        // generate() reads these with isset(), so null has always meant "fall back to the default".
        /** @psalm-suppress InvalidArgument We are deliberately passing a value the type forbids. */
        $output = $this->generator(['UID' => null, 'TRANSP' => null])->generate($this->createShortEventLink());

        $this->assertMatchesRegularExpression('/^UID:[0-9a-f]{32}\r$/m', $output);
        $this->assertStringNotContainsString('TRANSP:', $output);
    }

    /**
     * @return \Generator<string, array{mixed, string}>
     */
    public static function unwritableValueProvider(): \Generator
    {
        // Casting any of these would put something in the file that the caller never meant to write,
        // and an array or a plain object would raise a PHP warning or Error rather than ours.
        yield 'an array' => [[], 'array'];
        yield 'an object without __toString()' => [new \stdClass(), 'stdClass'];
        yield 'a float, whose string form follows the precision setting' => [1.5, 'float'];
        yield 'a bool, since false casts to an empty string' => [false, 'bool'];
    }

    #[Test]
    #[DataProvider('unwritableValueProvider')]
    public function it_rejects_a_value_it_cannot_faithfully_write(mixed $value, string $expectedType): void
    {
        $this->expectException(InvalidLink::class);
        $this->expectExceptionMessage("The `UID` option must be a string, an integer or a Stringable, `{$expectedType}` given.");

        /** @psalm-suppress MixedArgumentTypeCoercion We are deliberately passing a value the type forbids. */
        $this->generator(['UID' => $value]);
    }

    #[Test]
    public function it_rejects_a_value_it_cannot_faithfully_write_for_an_enumerated_property_too(): void
    {
        $this->expectException(InvalidLink::class);
        $this->expectExceptionMessage('The `TRANSP` option must be a string, an integer or a Stringable, `array` given.');

        /** @psalm-suppress InvalidArgument We are deliberately passing a value the type forbids. */
        $this->generator(['TRANSP' => []]);
    }

    #[Test]
    public function it_keeps_writing_an_integer_option(): void
    {
        // An integer has one unambiguous string form and cannot carry a line break, so it stays allowed.
        /** @psalm-suppress InvalidArgument We are deliberately passing a value the type forbids. */
        $output = $this->generator(['UID' => 123])->generate($this->createShortEventLink());

        $this->assertStringContainsString('UID:123', $output);
    }

    #[Test]
    public function it_escapes_a_custom_uid_and_product_id(): void
    {
        // Both are TEXT values, so their separators need escaping like any other TEXT field.
        // @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.8.4.7
        // @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.7.3
        $output = $this->generator(['UID' => 'part;one,two\\three', 'PRODID' => 'ACME, Inc.'])
            ->generate($this->createShortEventLink());

        $this->assertStringContainsString('UID:part\;one\,two\\\\three', $output);
        $this->assertStringContainsString('PRODID:ACME\, Inc.', $output);
    }

    #[Test]
    public function it_escapes_a_line_break_in_a_uid_or_a_product_id_rather_than_rejecting_it(): void
    {
        // Escaping turns the line break into the \n escape, so it cannot start a content line and
        // there is nothing left for the guard to reject.
        $output = $this->generator([
            'UID' => "x\r\nORGANIZER:mailto:attacker@example.com",
            'PRODID' => "y\nORGANIZER:mailto:attacker@example.com",
        ])->generate($this->createShortEventLink());

        $this->assertStringContainsString('UID:x\nORGANIZER:mailto:attacker@example.com', $output);
        $this->assertStringContainsString('PRODID:y\nORGANIZER:mailto:attacker@example.com', $output);
        $this->assertStringNotContainsString("\r\nORGANIZER", $output);
    }

    /**
     * @return \Generator<string, array{string, string, string}>
     */
    public static function lowercaseTokenProvider(): \Generator
    {
        yield 'TRANSP' => ['TRANSP', 'transparent', 'TRANSP:TRANSPARENT'];
        yield 'CLASS' => ['CLASS', 'private', 'CLASS:PRIVATE'];
        yield 'X-MICROSOFT-CDO-BUSYSTATUS' => ['X-MICROSOFT-CDO-BUSYSTATUS', 'oof', 'X-MICROSOFT-CDO-BUSYSTATUS:OOF'];
        yield 'mixed case' => ['TRANSP', 'OpAqUe', 'TRANSP:OPAQUE'];
    }

    #[Test]
    #[DataProvider('lowercaseTokenProvider')]
    public function it_accepts_an_enumerated_token_in_any_case_and_writes_it_upper_cased(string $property, string $token, string $expected): void
    {
        // An enumerated property value is case-insensitive.
        // @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.1
        /** @psalm-suppress ArgumentTypeCoercion The property and token pair is valid, but only known at runtime. */
        $output = $this->generator([$property => $token])->generate($this->createShortEventLink());

        $this->assertStringContainsString($expected, $output);
    }

    #[Test]
    public function it_keeps_a_line_break_out_of_the_rejection_message(): void
    {
        // The value cannot reach the calendar, but the message reporting it reaches a log, and a
        // forged line spread over several lines of one is the very thing this guard exists to stop.
        try {
            /** @psalm-suppress InvalidArgument We are deliberately passing a value the type forbids. */
            $this->generator(['TRANSP' => "OPAQUE\r\nX-FORGED:yes"]);
            $this->fail('Expected an InvalidLink to be thrown.');
        } catch (InvalidLink $exception) {
            $this->assertStringNotContainsString("\r", $exception->getMessage());
            $this->assertStringNotContainsString("\n", $exception->getMessage());
            $this->assertStringContainsString('OPAQUEX-FORGED:yes', $exception->getMessage());
        }
    }

    #[Test]
    public function it_percent_encodes_a_control_character_an_attendee_address_was_assigned_directly(): void
    {
        // guest() rejects this address, but $guests is public, so it can also be assigned around it.
        $link = $this->eventWithTitle('Birthday');
        $link->guests[] = ['email' => "guest@example.com\r\nORGANIZER:mailto:forged@example.com", 'optional' => false];

        $output = $this->unfold($this->generator()->generate($link));

        $this->assertStringContainsString('mailto:guest@example.com%0D%0AORGANIZER:mailto:forged@example.com', $output);
        $this->assertStringNotContainsString("\r\nORGANIZER:", $output);
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function controlCharacterProvider(): \Generator
    {
        yield 'URL' => ['URL', "https://example.com/\x00"];
        yield 'RRULE' => ['RRULE', "FREQ=WEEKLY\x07"];
    }

    #[Test]
    #[DataProvider('controlCharacterProvider')]
    public function it_rejects_a_control_character_in_a_property_it_cannot_escape(string $property, string $value): void
    {
        $this->expectException(InvalidLink::class);
        $this->expectExceptionMessage("ICS property (`{$property}`) must not contain a control character.");

        /** @psalm-suppress ArgumentTypeCoercion We are deliberately passing a value the type forbids. */
        $this->generator([$property => $value]);
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
