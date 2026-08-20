<?php

declare(strict_types=1);

namespace Spatie\CalendarLinks;

use Spatie\CalendarLinks\Exceptions\InvalidLink;
use Spatie\CalendarLinks\Generators\Google;
use Spatie\CalendarLinks\Generators\Ics;
use Spatie\CalendarLinks\Generators\WebOffice;
use Spatie\CalendarLinks\Generators\WebOutlook;
use Spatie\CalendarLinks\Generators\Yahoo;

/**
 * @api
 * @psalm-import-type IcsOptions from \Spatie\CalendarLinks\Generators\Ics
 * @psalm-import-type GoogleUrlParameters from \Spatie\CalendarLinks\Generators\Google
 * @psalm-import-type YahooUrlParameters from \Spatie\CalendarLinks\Generators\Yahoo
 * @psalm-import-type OutlookUrlParameters from \Spatie\CalendarLinks\Generators\BaseOutlook
 * @psalm-import-type IcsPresentationOptions from \Spatie\CalendarLinks\Generators\Ics
 * @psalm-type LinkGuest = array{email: string, optional: bool}
 */
class Link
{
    public readonly string $title;

    public readonly \DateTimeImmutable $from;

    public readonly \DateTimeImmutable $to;

    /** The timezone the event starts in. */
    public readonly \DateTimeZone $fromTimezone;

    /** The timezone the event ends in, which is only distinct from $fromTimezone for something like a flight. */
    public readonly \DateTimeZone $toTimezone;

    public readonly bool $allDay;

    public string $description = '';

    public string $address = '';

    /** @psalm-var list<LinkGuest> */
    public array $guests = [];

    final public function __construct(string $title, \DateTimeInterface $from, \DateTimeInterface $to, bool $allDay = false)
    {
        $this->title = $title;
        $this->allDay = $allDay;

        // Recorded before the normalisation below folds $to into $from's zone and the identity is lost.
        $this->fromTimezone = $from->getTimezone();
        $this->toTimezone = $to->getTimezone();

        $this->from = \DateTimeImmutable::createFromInterface($from);

        $immutableTo = \DateTimeImmutable::createFromInterface($to);

        // Ensures timezones match. This asks a narrower question than hasDistinctTimezones(): not
        // whether the two zones are worth naming separately, but whether $to needs moving at all.
        // Equal names mean there is nothing to move, and for every other pair the conversion is
        // either meaningful or a no-op, so the cheap comparison is the right one here. It leaves
        // $to always carried in $fromTimezone, which the generators rely on when they render both
        // endpoints under a single zone.
        if ($this->fromTimezone->getName() !== $this->toTimezone->getName()) {
            $immutableTo = $allDay
                ? self::reinterpretIn($immutableTo, $this->fromTimezone)
                : $immutableTo->setTimezone($this->fromTimezone);
        }

        // Ensures from date is earlier than to date.
        if ($this->from > $immutableTo) {
            throw InvalidLink::negativeDateRange($this->from, $immutableTo);
        }

        // All-day events: convert inclusive end date to exclusive end date,
        // as calendar services expect the end date to be the day after the last event day.
        $this->to = $allDay ? $immutableTo->modify('+1 day') : $immutableTo;
    }

    /**
     * An all-day event has no clock time, so each endpoint is a calendar date rather than an instant.
     * Reading the date the caller wrote in another zone keeps that date, where converting the instant
     * would move it to whichever date the same moment falls on in the start's zone, gaining or losing
     * a day. The format holds no offset, so the zone passed alongside it is the one that applies.
     */
    private static function reinterpretIn(\DateTimeImmutable $date, \DateTimeZone $timezone): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date->format('Y-m-d H:i:s'), $timezone);
    }

    /**
     * @throws \Spatie\CalendarLinks\Exceptions\InvalidLink When date range is invalid.
     */
    public static function create(string $title, \DateTimeInterface $from, \DateTimeInterface $to): static
    {
        return new static($title, $from, $to);
    }

    /**
     * @param positive-int $numberOfDays
     * @throws \Spatie\CalendarLinks\Exceptions\InvalidLink When date range is invalid.
     */
    public static function createAllDay(string $title, \DateTimeInterface $from, int $numberOfDays = 1): static
    {
        $lastDay = $numberOfDays - 1;
        $to = \DateTimeImmutable::createFromInterface($from)->modify("+$lastDay days");

        return new static($title, $from, $to, true);
    }

    /** Set the description of the Event. */
    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /** Set the address of the Event. */
    public function address(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    /**
     * Add a guest (attendee) to the Event.
     *
     * An address already on the list is ignored, so the first spelling and role of a guest are the
     * ones that are kept.
     *
     * @param string $email A plain email address. Display names are not supported, because Yahoo cannot represent them.
     * @param bool $optional Whether attendance is optional. Yahoo has no optional role, so optional guests are invited as required there.
     * @throws \Spatie\CalendarLinks\Exceptions\InvalidLink When the email address is invalid.
     */
    public function guest(string $email, bool $optional = false): static
    {
        $email = self::validateGuestEmail($email);

        if (! $this->hasGuest($email)) {
            $this->guests[] = ['email' => $email, 'optional' => $optional];
        }

        return $this;
    }

    /**
     * Add several guests (attendees) to the Event at once. Duplicates are ignored, both within the
     * batch and against guests already added.
     *
     * @param list<string> $emails Plain email addresses.
     * @param bool $optional Whether attendance is optional for all of them.
     * @throws \Spatie\CalendarLinks\Exceptions\InvalidLink When any of the email addresses is invalid.
     */
    public function guests(array $emails, bool $optional = false): static
    {
        // Validate every address up front, so a rejected one leaves the guest list untouched.
        foreach ($emails as $email) {
            self::validateGuestEmail($email);
        }

        foreach ($emails as $email) {
            $this->guest($email, $optional);
        }

        return $this;
    }

    /**
     * The whole address is compared case-insensitively. A local part is technically case-sensitive
     * per RFC 5321, but no calendar service treats it as such, so two spellings of one address would
     * invite the same person twice. FILTER_VALIDATE_EMAIL has already rejected anything non-ASCII,
     * so a byte-wise fold is enough.
     */
    private function hasGuest(string $email): bool
    {
        foreach ($this->guests as $guest) {
            if (strcasecmp($guest['email'], $email) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * A quoted local part (`"foo,bar"@example.com`) passes FILTER_VALIDATE_EMAIL, but none of the
     * services can carry one: Google and Yahoo both use a comma as their address separator, and no
     * calendar accepts a quoted address anyway. Rejecting it keeps every generator in agreement.
     *
     * @throws \Spatie\CalendarLinks\Exceptions\InvalidLink When the email address is invalid.
     */
    private static function validateGuestEmail(string $email): string
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || str_starts_with($email, '"')) {
            throw InvalidLink::invalidGuestEmail($email);
        }

        return $email;
    }

    /**
     * Whether the event genuinely starts and ends in different timezones, so a generator that can
     * express both should. An all-day event has no clock time to place in a zone, so it never does.
     *
     * Two spellings of one zone do not count. `UTC`, `Etc/UTC` and the bare `Z` that PHP leaves on a
     * Z suffixed ISO string all describe the same zone, and emitting them as a pair hands the
     * services identifiers they reject for an event that never crossed a zone at all.
     *
     * Equal offsets alone are not the test though. Europe/London and Europe/Lisbon share an offset
     * the whole year, as do Europe/Berlin and Africa/Lagos in winter, yet each pair is two places a
     * flight may legitimately want to name. So the zones collapse into one only when their offsets
     * agree and at least one of them names no place: a bare offset, an abbreviation, and every
     * member of the UTC family are all just an offset wearing a label, with no location to lose.
     */
    public function hasDistinctTimezones(): bool
    {
        if ($this->allDay || $this->fromTimezone->getName() === $this->toTimezone->getName()) {
            return false;
        }

        // Each zone is read at the instant that belongs to it, so an event running across a DST
        // change still sees the two offsets it actually spans.
        if ($this->fromTimezone->getOffset($this->from) !== $this->toTimezone->getOffset($this->to)) {
            return true;
        }

        return self::namesAPlace($this->fromTimezone) && self::namesAPlace($this->toTimezone);
    }

    /**
     * Spellings of UTC that the timezone database lists as identifiers in their own right. The
     * `Etc/` tree is matched by prefix instead, since every zone in it is a fixed offset. PHP hands
     * back whatever case the caller wrote, so these are compared lowercased.
     */
    private const array UTC_ZONE_NAMES = ['utc', 'zulu', 'universal', 'greenwich', 'gmt0'];

    /**
     * Whether a zone stands for a place rather than a plain offset.
     *
     * `getLocation()` returns false for the zones the database holds no location row for at all: a
     * bare offset (`+01:00`), the abbreviation PHP leaves on a Z suffixed ISO string, and the
     * standalone abbreviations (`GMT`, `UCT`, `CET`, `EST`, `MST`, `HST`, `MET`, `WET`, `EET`).
     *
     * The country code deliberately plays no part in the decision. Every IANA backward alias reports
     * the same `??` placeholder as the UTC family does, so reading `??` as "no place" would quietly
     * classify `Japan`, `Singapore`, `US/Eastern`, `GB` and `Asia/Calcutta` as offsets, and a Tokyo
     * to Seoul flight booked with `new DateTimeZone('Japan')` would lose its destination. The rest of
     * the offset family is therefore named outright rather than inferred.
     *
     * `EST5EDT`, `CST6CDT`, `MST7MDT`, `PST8PDT` and `Factory` are left out of that set on purpose.
     * They name no place either, but they carry daylight saving rules rather than one fixed offset,
     * so they are more than a label on an offset. Counting them as places costs at most a second
     * TZID that every service still resolves, where counting them as placeless would drop the real
     * zone they are paired with, which is the failure this guards against.
     */
    private static function namesAPlace(\DateTimeZone $timezone): bool
    {
        if ($timezone->getLocation() === false) {
            return false;
        }

        $name = strtolower($timezone->getName());

        return ! in_array($name, self::UTC_ZONE_NAMES, true) && ! str_starts_with($name, 'etc/');
    }

    public function formatWith(Generator $generator): string
    {
        return $generator->generate($this);
    }

    /** @psalm-param GoogleUrlParameters $urlParameters */
    public function google(array $urlParameters = []): string
    {
        return $this->formatWith(new Google($urlParameters));
    }

    /**
     * @psalm-param IcsOptions $options ICS specific properties and components
     * @psalm-param IcsPresentationOptions $presentationOptions
     * @return string
     * @throws \Spatie\CalendarLinks\Exceptions\InvalidLink When an option value cannot be written to the calendar.
     */
    public function ics(array $options = [], array $presentationOptions = []): string
    {
        return $this->formatWith(new Ics($options, $presentationOptions));
    }

    /** @psalm-param YahooUrlParameters $urlParameters */
    public function yahoo(array $urlParameters = []): string
    {
        return $this->formatWith(new Yahoo($urlParameters));
    }

    /** @psalm-param OutlookUrlParameters $urlParameters */
    public function webOutlook(array $urlParameters = []): string
    {
        return $this->formatWith(new WebOutlook($urlParameters));
    }

    /** @psalm-param OutlookUrlParameters $urlParameters */
    public function webOffice(array $urlParameters = []): string
    {
        return $this->formatWith(new WebOffice($urlParameters));
    }
}
