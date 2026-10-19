<?php

declare(strict_types=1);

use Spatie\CalendarLinks\Generators\Ics;
use Spatie\CalendarLinks\Link;

it('names a shared resolvable timezone with one complete definition', function (string $zone, string $offset): void {
    assert($zone !== '');
    $from = new DateTimeImmutable('2026-01-15 09:10:00', new DateTimeZone($zone));
    $link = Link::create('Meeting', $from, $from->modify('+45 minutes'));

    $output = $link->ics([], ['format' => Ics::FORMAT_FILE]);

    expect($output)
        ->toContain("DTSTART;TZID={$zone}:20260115T091000\r\n")
        ->toContain("DTEND;TZID={$zone}:20260115T095500\r\n")
        ->toContain("BEGIN:VTIMEZONE\r\nTZID:{$zone}\r\n")
        ->toContain("TZOFFSETTO:{$offset}\r\n")
        ->and(substr_count($output, 'BEGIN:VTIMEZONE'))->toBe(1)
        ->and(substr_count($output, 'END:VTIMEZONE'))->toBe(1);

    $uri = $link->ics();
    expect(base64_decode(substr($uri, strlen('data:text/calendar;charset=utf-8;base64,')), true))->toBe($output);
})->with([
    'UTC' => ['UTC', '+0000'],
    'DST region' => ['Europe/Brussels', '+0100'],
    'fixed region' => ['Asia/Tokyo', '+0900'],
    'fractional offset' => ['Asia/Kathmandu', '+0545'],
    'legacy alias' => ['Japan', '+0900'],
]);

it('preserves exact instants around backward clock changes', function (
    string $startZone,
    string $endZone,
    string $fromUtc,
    string $toUtc,
    string $expectedStart,
    string $expectedEnd,
    int $definitions,
): void {
    assert($startZone !== '');
    assert($endZone !== '');
    $from = (new DateTimeImmutable($fromUtc))->setTimezone(new DateTimeZone($startZone));
    $to = (new DateTimeImmutable($toUtc))->setTimezone(new DateTimeZone($endZone));
    $link = Link::create('Clock change', $from, $to);

    $output = $link->ics([], ['format' => Ics::FORMAT_FILE]);

    expect($output)
        ->toContain("{$expectedStart}\r\n")
        ->toContain("{$expectedEnd}\r\n")
        ->and(substr_count($output, 'BEGIN:VTIMEZONE'))->toBe($definitions);
})->with([
    'first occurrence stays named' => ['Europe/Brussels', 'Europe/Brussels', '2026-10-25T00:30:00Z', '2026-10-25T00:45:00Z', 'DTSTART;TZID=Europe/Brussels:20261025T023000', 'DTEND;TZID=Europe/Brussels:20261025T024500', 1],
    'second occurrence stays exact' => ['Europe/Brussels', 'Europe/Brussels', '2026-10-25T01:30:00Z', '2026-10-25T01:45:00Z', 'DTSTART:20261025T013000Z', 'DTEND:20261025T014500Z', 0],
    'only end is repeated' => ['Europe/Brussels', 'Europe/Brussels', '2026-10-25T00:45:00Z', '2026-10-25T01:15:00Z', 'DTSTART:20261025T004500Z', 'DTEND:20261025T011500Z', 0],
    'only start is repeated' => ['Europe/Brussels', 'Europe/Brussels', '2026-10-25T01:45:00Z', '2026-10-25T02:15:00Z', 'DTSTART:20261025T014500Z', 'DTEND:20261025T021500Z', 0],
    'rollback instant is included' => ['Europe/Brussels', 'Europe/Brussels', '2026-10-25T01:00:00Z', '2026-10-25T01:00:01Z', 'DTSTART:20261025T010000Z', 'DTEND:20261025T010001Z', 0],
    'overlap end is excluded' => ['Europe/Brussels', 'Europe/Brussels', '2026-10-25T02:00:00Z', '2026-10-25T02:10:00Z', 'DTSTART;TZID=Europe/Brussels:20261025T030000', 'DTEND;TZID=Europe/Brussels:20261025T031000', 1],
    'half hour rollback' => ['Australia/Lord_Howe', 'Australia/Lord_Howe', '2026-04-04T15:15:00Z', '2026-04-04T15:20:00Z', 'DTSTART:20260404T151500Z', 'DTEND:20260404T152000Z', 0],
    'half hour overlap end' => ['Australia/Lord_Howe', 'Australia/Lord_Howe', '2026-04-04T15:30:00Z', '2026-04-04T15:40:00Z', 'DTSTART;TZID=Australia/Lord_Howe:20260405T020000', 'DTEND;TZID=Australia/Lord_Howe:20260405T021000', 1],
    'arrival zone is checked' => ['Asia/Tokyo', 'America/New_York', '2026-11-01T00:00:00Z', '2026-11-01T06:30:00Z', 'DTSTART:20261101T000000Z', 'DTEND:20261101T063000Z', 0],
    'discarded end alias uses start zone' => ['Europe/Brussels', '+01:00', '2026-10-25T01:10:00Z', '2026-10-25T01:20:00Z', 'DTSTART:20261025T011000Z', 'DTEND:20261025T012000Z', 0],
    'date-line rollback' => ['Pacific/Kwajalein', 'Pacific/Kwajalein', '1969-10-01T10:00:00Z', '1969-10-01T10:15:00Z', 'DTSTART:19691001T100000Z', 'DTEND:19691001T101500Z', 0],
    'date-line overlap end' => ['Pacific/Kwajalein', 'Pacific/Kwajalein', '1969-10-01T12:00:00Z', '1969-10-01T12:15:00Z', 'DTSTART;TZID=Pacific/Kwajalein:19691001T000000', 'DTEND;TZID=Pacific/Kwajalein:19691001T001500', 1],
    'spring forward stays named' => ['Europe/Brussels', 'Europe/Brussels', '2026-03-29T00:45:00Z', '2026-03-29T01:15:00Z', 'DTSTART;TZID=Europe/Brussels:20260329T014500', 'DTEND;TZID=Europe/Brussels:20260329T031500', 1],
]);

it('keeps an unrepresentable recurring start exact rather than moving its first occurrence', function (): void {
    $from = (new DateTimeImmutable('2026-10-25T01:30:00Z'))->setTimezone(new DateTimeZone('Europe/Brussels'));
    $link = Link::create('Weekly meeting', $from, $from->modify('+15 minutes'));

    $output = $link->ics(['RRULE' => 'FREQ=WEEKLY;COUNT=3'], ['format' => Ics::FORMAT_FILE]);

    expect($output)
        ->toContain("DTSTART:20261025T013000Z\r\n")
        ->toContain("DTEND:20261025T014500Z\r\n")
        ->toContain("RRULE:FREQ=WEEKLY;COUNT=3\r\n")
        ->and(str_contains($output, 'TZID'))->toBeFalse();
});

it('keeps all-day events date-only in a named DST region', function (): void {
    $link = Link::createAllDay('Holiday', new DateTimeImmutable('2026-10-25', new DateTimeZone('Europe/Brussels')), 2);

    $output = $link->ics([], ['format' => Ics::FORMAT_FILE]);

    expect($output)
        ->toContain("DTSTART;VALUE=DATE:20261025\r\n")
        ->toContain("DURATION:P2D\r\n")
        ->toContain("DTSTAMP:20261024T220000Z\r\n")
        ->and(str_contains($output, 'TZID'))->toBeFalse()
        ->and(str_contains($output, 'VTIMEZONE'))->toBeFalse();
});
