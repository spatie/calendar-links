<?php

declare(strict_types=1);

namespace Spatie\CalendarLinks\Tests\Generators;

use DateTime;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
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
