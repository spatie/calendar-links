<?php

declare(strict_types=1);

namespace Spatie\CalendarLinks\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use Spatie\CalendarLinks\Generators\Ics;
use Spatie\CalendarLinks\Link;

/**
 * Every timezone defect this library has carried had the same shape: a category of DateTimeZone name
 * nobody had thought of. Each was found by hand, one at a time, and each needed its own targeted fix
 * and its own targeted test. The tests here ask the same question of every name at once, so the next
 * category is answered by a test run rather than by whoever happens to be reading the diff.
 *
 * Each one sweeps every name the database ships plus the offset and abbreviation spellings that only
 * DateTimeZone accepts, collects what it finds wrong, and asserts it found nothing, so one run names
 * every zone that broke rather than stopping at the first.
 *
 * Nothing here is measured against a hardcoded transition date. The tzdb ships with PHP and moves,
 * and the CI matrix spans three PHP versions that may carry different releases of it, so every
 * expectation is asked of DateTimeZone at runtime instead. That difference is the point: a wider
 * spread of tzdata widens the search rather than breaking the suite.
 *
 * The targeted regression tests these complement stay where they are, since they document why each
 * individual rule exists.
 */
final class TimezoneInvariantsTest extends TestCase
{
    /**
     * Names DateTimeZone accepts that listIdentifiers() does not return, one of each category that
     * has caused a defect: a bare offset either side of UTC, the abbreviation PHP leaves on a Z
     * suffixed ISO string, a daylight saving abbreviation, and the lowercase spelling that keeps its
     * literal name where the uppercase one normalises to `+00:00`. `GMT` and `UTC` are shipped names
     * and appear in the list already, so they are not repeated here.
     */
    private const array UNLISTED_TIMEZONE_NAMES = ['+02:00', '-05:00', 'Z', 'CEST', 'gmt+0', 'gmt-0'];

    /** The zone the flight shape below flies to, and the one it flies to when the sweep is on that zone itself. */
    private const string PARTNER_TIMEZONE_NAME = 'Asia/Tokyo';

    private const string ALTERNATE_PARTNER_TIMEZONE_NAME = 'America/Los_Angeles';

    /**
     * The local time every swept event starts at. Any real date does, since everything compared
     * against it is derived from DateTimeZone rather than written down. It does need to be a time no
     * zone changes its clocks at, though: a local time inside a daylight saving overlap names two
     * instants, and the file could then resolve it to the other one and still be right. A tzdata
     * release that puts a transition at 09:00 on this date somewhere would show up as a resolution
     * failure for that zone, and moving this is the fix.
     */
    private const string EVENT_START = '2026-05-15 09:00';

    /**
     * Every name the database ships, deprecated ones included, followed by the spellings above, and
     * only the ones a DateTimeZone can actually be built from.
     *
     * That last filter is not redundant. A PHP linked against the system timezone database, which is
     * how most distributions build it and how the CI runners get theirs, enumerates the files in its
     * zoneinfo directory rather than a list of zones, and that directory holds more than zones:
     * `leapseconds` is listed there and DateTimeZone refuses to construct one. Since a name that
     * cannot become a DateTimeZone cannot reach this library either, it is out of scope rather than a
     * defect, and it is recognised by asking rather than by naming it, because another build may well
     * ship a different set of such files.
     *
     * @return list<non-empty-string>
     */
    private static function timezoneNames(): array
    {
        /** @var list<non-empty-string> $candidates */
        $candidates = [...DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), ...self::UNLISTED_TIMEZONE_NAMES];

        $names = [];

        foreach ($candidates as $name) {
            try {
                new DateTimeZone($name);
            } catch (\DateInvalidTimeZoneException) {
                continue;
            }

            $names[$name] = true;
        }

        return array_keys($names);
    }

    /**
     * The event shapes one zone is swept with, keyed by a name the failure messages can carry. They
     * are three because each reaches a branch the others do not: only the flight writes a zone into
     * the output as a TZID, and only the all-day event names a zone without a clock time to place in
     * it.
     *
     * @param non-empty-string $timezoneName
     * @return array<string, Link>
     */
    private static function linksIn(string $timezoneName): array
    {
        $timezone = new DateTimeZone($timezoneName);
        $start = new DateTimeImmutable(self::EVENT_START, $timezone);

        $partner = new DateTimeZone($timezone->getName() === self::PARTNER_TIMEZONE_NAME
            ? self::ALTERNATE_PARTNER_TIMEZONE_NAME
            : self::PARTNER_TIMEZONE_NAME);

        return [
            'timed' => Link::create('Standup', $start, $start->modify('+2 hours')),
            // The arrival is derived from the departure instant rather than written as a local time,
            // so the range stays positive whichever offset the partner zone happens to be on.
            'flight' => Link::create('Flight', $start, $start->setTimezone($partner)->modify('+11 hours')),
            'all-day' => Link::createAllDay('Holiday', $start->setTime(0, 0), 3),
        ];
    }

    /**
     * Every generator's output for one link. The ICS is asked for as a file rather than a data URI,
     * so the assertions can read it.
     *
     * @return array<string, string>
     */
    private function generatedFor(Link $link): array
    {
        return [
            'google' => $link->google(),
            'ics' => $link->ics([], ['format' => Ics::FORMAT_FILE]),
            'yahoo' => $link->yahoo(),
            'webOutlook' => $link->webOutlook(),
            'webOffice' => $link->webOffice(),
        ];
    }

    /**
     * The file an Ics subclass produces when it writes a component for one zone whatever the event's
     * own zones are. Both hooks it reaches through are documented extension points, and this is the
     * only path that reaches a zone no endpoint can name: an offset or an abbreviation never gets a
     * TZID of its own, so a component is only ever written for one because a subclass asked for it.
     * That is the path the empty component and its warning came down.
     *
     * @param non-empty-string $timezoneName
     */
    private function forcedComponentFor(string $timezoneName): string
    {
        $generator = new class ([], ['format' => Ics::FORMAT_FILE]) extends Ics {
            /** @var non-empty-string */
            public string $timezoneName = 'UTC';

            #[\Override]
            protected function shouldDefineTimezones(Link $link): bool
            {
                return true;
            }

            #[\Override]
            protected function referencedTimezones(Link $link): array
            {
                return [new DateTimeZone($this->timezoneName)];
            }
        };

        $generator->timezoneName = $timezoneName;

        return $generator->generate(self::linksIn('UTC')['timed']);
    }

    /**
     * The outputs of every generator, with any PHP diagnostic raised along the way left unreported.
     *
     * The four content invariants below ask what the generators wrote, which is a different question
     * from whether they complained on the way. Letting a diagnostic through here would end the run on
     * the first zone that raised one and hide every zone after it, and escalating it to an exception
     * would throw away the very output the invariant is about: the empty VTIMEZONE that a warning
     * accompanies is only visible if the warning does not take the file with it. The last test in this
     * file is the one that judges diagnostics, against every zone at once.
     *
     * @return array<string, string>
     */
    private function outputsOf(Link $link): array
    {
        return $this->withDiagnosticsIgnored(fn (): array => $this->generatedFor($link));
    }

    /**
     * The forced component, with diagnostics likewise left to the test that judges them.
     *
     * @param non-empty-string $timezoneName
     * @see self::outputsOf()
     */
    private function forcedComponentQuietlyFor(string $timezoneName): string
    {
        return $this->withDiagnosticsIgnored(fn (): string => $this->forcedComponentFor($timezoneName));
    }

    /**
     * Runs the given generation with every PHP warning, notice and deprecation swallowed, so that what
     * it returns can be judged on its own terms.
     *
     * @template TOutput
     * @param callable(): TOutput $generate
     * @return TOutput
     */
    private function withDiagnosticsIgnored(callable $generate): mixed
    {
        set_error_handler(static fn (): bool => true);

        try {
            return $generate();
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Runs the given generation with every PHP warning, notice and deprecation turned into an
     * exception, so the sweep that judges them can name the zone that raised one.
     *
     * @template TOutput
     * @param callable(): TOutput $generate
     * @return TOutput
     * @throws \ErrorException
     */
    private function withDiagnosticsEscalated(callable $generate): mixed
    {
        set_error_handler(static function (int $severity, string $message): never {
            throw new \ErrorException($message, severity: $severity);
        });

        try {
            return $generate();
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Rebuilds the original content lines, so a value the writer folded across two of them can be
     * matched in one piece.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.1
     */
    private static function unfold(string $ics): string
    {
        return str_replace("\r\n ", '', $ics);
    }

    /**
     * The endpoints written with a TZID parameter, as [property, TZID value, remaining value] triples.
     *
     * The parameter is read the way a client reads it, stopping at the first colon, which is where a
     * param-value ends. A name carrying a colon therefore comes back cut in half, with the rest of it
     * left in the third element, which is the failure it causes in a real client rather than a
     * contrived one.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.1
     * @return list<array{string, string, string}>
     */
    private static function namedEndpointsOf(string $ics): array
    {
        preg_match_all('/^(DTSTART|DTEND);TZID=([^:\r\n]*):([^\r\n]*)/m', $ics, $endpoints, PREG_SET_ORDER);

        return array_map(
            static fn (array $endpoint): array => [$endpoint[1], $endpoint[2], $endpoint[3]],
            $endpoints
        );
    }

    /**
     * The body of every VTIMEZONE component in a file, keyed by the TZID it defines.
     *
     * @return array<string, string>
     */
    private static function timezoneComponentsOf(string $ics): array
    {
        preg_match_all("/BEGIN:VTIMEZONE\r\nTZID:([^\r\n]*)\r\n(.*?)END:VTIMEZONE/s", $ics, $components, PREG_SET_ORDER);

        /** @var array<string, string> $bodies */
        $bodies = array_column($components, 2, 1);

        return $bodies;
    }

    /**
     * The offset a client reads a local time back at: the observance with the latest onset at or
     * before it, taken across the whole component rather than per kind of observance. Null when the
     * component states nothing that applies, which leaves the client with no offset to use.
     *
     * The onsets are written in chronological order and in a fixed width format, so comparing them as
     * strings orders them correctly.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.6.5
     */
    private static function offsetResolvedBy(string $component, string $localDateTime): ?int
    {
        preg_match_all(
            "/DTSTART:(\d{8}T\d{6})\r\nTZOFFSETFROM:\S+\r\nTZOFFSETTO:([+-]\d{4,6})/",
            $component,
            $observances,
            PREG_SET_ORDER
        );

        $applicable = null;

        foreach ($observances as $observance) {
            if ($observance[1] <= $localDateTime) {
                $applicable = $observance[2];
            }
        }

        return $applicable === null ? null : self::offsetInSeconds($applicable);
    }

    /**
     * Reads a TZOFFSETTO value back into seconds. The seconds field is only written by the zones that
     * need it, so it is absent from most values and reads as zero.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.3.14
     */
    private static function offsetInSeconds(string $offset): int
    {
        $seconds = 3600 * (int) substr($offset, 1, 2)
            + 60 * (int) substr($offset, 3, 2)
            + (int) substr($offset, 5, 2);

        return $offset[0] === '-' ? -$seconds : $seconds;
    }

    #[Test]
    public function it_writes_the_whole_zone_name_into_every_tzid_parameter(): void
    {
        // A param-value ends at the first colon unless it is quoted, so an offset style name written
        // into one cuts the property value in half and leaves the client reading `+02` as the zone
        // and `00:20260515T090000` as the date. Comparing what a client would read back against the
        // name the link carries catches that without needing to name the offending characters.
        // @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.1
        $violations = [];

        foreach (self::timezoneNames() as $timezoneName) {
            foreach (self::linksIn($timezoneName) as $shape => $link) {
                $ics = self::unfold($this->outputsOf($link)['ics']);

                foreach (self::namedEndpointsOf($ics) as [$property, $tzid, $remainder]) {
                    $expected = $property === 'DTSTART'
                        ? $link->fromTimezone->getName()
                        : $link->toTimezone->getName();

                    if ($tzid !== $expected) {
                        $violations[] = "$timezoneName ($shape): $property names `$tzid`, expected `$expected`";
                    }

                    if (preg_match('/^\d{8}T\d{6}$/', $remainder) !== 1) {
                        $violations[] = "$timezoneName ($shape): $property carries `$remainder`, which is not a local date-time";
                    }
                }
            }
        }

        $this->assertSame([], $violations);
    }

    #[Test]
    public function it_gives_every_timezone_component_at_least_one_observance(): void
    {
        // "A VTIMEZONE calendar component MUST include at least one definition of a STANDARD or
        // DAYLIGHT subcomponent." A zone with no transition table for PHP to return is how a
        // component with nothing between its BEGIN and END came about.
        // @see https://datatracker.ietf.org/doc/html/rfc5545#section-3.6.5
        $violations = [];

        foreach (self::timezoneNames() as $timezoneName) {
            $files = ['forced' => $this->forcedComponentQuietlyFor($timezoneName)];

            foreach (self::linksIn($timezoneName) as $shape => $link) {
                $files[$shape] = $this->outputsOf($link)['ics'];
            }

            foreach ($files as $shape => $ics) {
                foreach (self::timezoneComponentsOf(self::unfold($ics)) as $tzid => $component) {
                    if (! str_contains($component, 'BEGIN:STANDARD') && ! str_contains($component, 'BEGIN:DAYLIGHT')) {
                        $violations[] = "$timezoneName ($shape): the component for `$tzid` states no observance";
                    }
                }
            }
        }

        $this->assertSame([], $violations);
    }

    #[Test]
    public function it_resolves_every_named_endpoint_to_the_offset_php_reports(): void
    {
        // The endpoint is a local time, so the file only places it correctly if the observances it
        // carries resolve it to the offset the zone was actually on at that instant. Africa/Casablanca
        // is the case that made this worth asserting: it suspends its summer time for Ramadan and
        // restores it weeks later, so a component holding one observance of each kind drops the
        // restoration and moves the event an hour.
        $violations = [];

        foreach (self::timezoneNames() as $timezoneName) {
            foreach (self::linksIn($timezoneName) as $shape => $link) {
                $ics = self::unfold($this->outputsOf($link)['ics']);
                $components = self::timezoneComponentsOf($ics);

                foreach (self::namedEndpointsOf($ics) as [$property, $tzid, $localDateTime]) {
                    // A name or a value that it_writes_the_whole_zone_name_into_every_tzid_parameter()
                    // already reports as malformed is left to it.
                    if (! isset($components[$tzid]) || preg_match('/^\d{8}T\d{6}$/', $localDateTime) !== 1) {
                        continue;
                    }

                    // The end is carried in the start's zone, so it is read back in its own before the
                    // offset it belongs to is asked for.
                    [$moment, $timezone] = $property === 'DTSTART'
                        ? [$link->from, $link->fromTimezone]
                        : [$link->to->setTimezone($link->toTimezone), $link->toTimezone];

                    $resolved = self::offsetResolvedBy($components[$tzid], $localDateTime);
                    $expected = $timezone->getOffset($moment);

                    if ($resolved === null) {
                        $violations[] = "$timezoneName ($shape): the component for `$tzid` opens after $localDateTime, so nothing resolves it";

                        continue;
                    }

                    if ($resolved !== $expected) {
                        $violations[] = "$timezoneName ($shape): `$tzid` resolves $localDateTime to {$resolved}s, but PHP reports {$expected}s";
                    }
                }
            }
        }

        $this->assertSame([], $violations);
    }

    #[Test]
    public function it_names_no_timezone_the_library_calls_unresolvable(): void
    {
        // Google silently ignores a ctz, stz or etz it cannot resolve, which leaves the local times in
        // `dates` to be read in whichever zone the viewer sits in, so the event lands at the wrong
        // instant for everyone else. This proves the generator obeys the library's own rule about
        // which names are worth emitting. Whether that rule matches what Google actually resolves is
        // a separate question, and one no offline test can answer.
        $violations = [];

        foreach (self::timezoneNames() as $timezoneName) {
            foreach (self::linksIn($timezoneName) as $shape => $link) {
                $url = $this->outputsOf($link)['google'];
                preg_match_all('/&(ctz|stz|etz)=([^&]*)/', $url, $parameters, PREG_SET_ORDER);

                foreach ($parameters as [, $parameter, $name]) {
                    if (! $link->hasResolvableTimezones()) {
                        $violations[] = "$timezoneName ($shape): $parameter=$name, though the link names no zone a service can resolve";

                        continue;
                    }

                    if (! in_array($name, [$link->fromTimezone->getName(), $link->toTimezone->getName()], true)) {
                        $violations[] = "$timezoneName ($shape): $parameter=$name names neither of the link's zones";
                    }

                    // The generator writes the name into the query unencoded, on the grounds that
                    // every name reaching that point is spelled with unreserved characters and at
                    // most a `/`. One that is not arrives changed rather than merely unresolved: a
                    // `+` decodes to a space and a `:` is reserved. This is the one part of the rule
                    // that holds whatever the library decides a resolvable name is.
                    // @see https://datatracker.ietf.org/doc/html/rfc3986#section-2.3
                    if (preg_match('#^[A-Za-z0-9\-._~/]+$#', $name) !== 1) {
                        $violations[] = "$timezoneName ($shape): $parameter=$name is not spelled with characters a query carries unencoded";
                    }
                }
            }
        }

        $this->assertSame([], $violations);
    }

    #[Test]
    public function it_raises_no_php_diagnostic_for_any_timezone(): void
    {
        // The warning is the shape one of these defects took outright: getTransitions() returns false
        // for a zone with no transition table, and reading that unguarded warned and wrote an empty
        // component. The sweeps above deliberately look past a diagnostic so they can judge the output
        // it came with, which leaves this one to judge the diagnostic itself. Catching rather than
        // letting it escape names every zone that raised one, instead of ending the run on the first.
        $violations = [];

        foreach (self::timezoneNames() as $timezoneName) {
            $sweeps = ['forced' => fn (): array => ['ics' => $this->forcedComponentFor($timezoneName)]];

            foreach (self::linksIn($timezoneName) as $shape => $link) {
                $sweeps[$shape] = fn (): array => $this->generatedFor($link);
            }

            foreach ($sweeps as $shape => $sweep) {
                try {
                    $outputs = $this->withDiagnosticsEscalated($sweep);
                } catch (\ErrorException $diagnostic) {
                    $violations[] = "$timezoneName ($shape): ".$diagnostic->getMessage();

                    continue;
                }

                foreach ($outputs as $generator => $output) {
                    if ($output === '') {
                        $violations[] = "$timezoneName ($shape): $generator produced nothing";
                    }
                }
            }
        }

        $this->assertSame([], $violations);
    }
}
