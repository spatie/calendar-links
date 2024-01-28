<?php declare(strict_types=1);

namespace Spatie\CalendarLinks;

use Spatie\CalendarLinks\Exceptions\InvalidLink;
use Spatie\CalendarLinks\Generators\Google;
use Spatie\CalendarLinks\Generators\Ics;
use Spatie\CalendarLinks\Generators\WebOffice;
use Spatie\CalendarLinks\Generators\WebOutlook;
use Spatie\CalendarLinks\Generators\Yahoo;

/**
 * @psalm-import-type IcsOptions from \Spatie\CalendarLinks\Generators\Ics
 */
class Link
{
    public readonly string $title;

    public readonly \DateTimeImmutable $from;

    public readonly \DateTimeImmutable $to;

    public readonly bool $allDay;

    public string $description = '';

    public string $address = '';

    final public function __construct(string $title, \DateTimeInterface $from, \DateTimeInterface $to, bool $allDay = false)
    {
        $this->title = $title;
        $this->allDay = $allDay;

        if ($from > $to) {
            throw InvalidLink::negativeDateRange($from, $to);
        }

        $this->from = \DateTimeImmutable::createFromInterface($from);
        $this->to = \DateTimeImmutable::createFromInterface($to);
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
    public static function createAllDay(string $title, \DateTimeInterface $fromDate, int $numberOfDays = 1): static
    {
        // In cases where the from date is not UTC, make sure it's UTC, size all day events are floating and non UTC dates cause bugs in the generators
        if ($fromDate->getTimezone() !== new \DateTimeZone('UTC')) {
            $fromDate = \DateTimeImmutable::createFromFormat('Y-m-d', $fromDate->format('Y-m-d'));
        }

        $from = \DateTimeImmutable::createFromInterface($fromDate)->modify('midnight');
        if (! $from instanceof \DateTimeImmutable) {
            throw new InvalidLink('Could not modify $fromDate while building all day link.');
        }

        $to = $from->modify("+$numberOfDays days");
        if (! $to instanceof \DateTimeImmutable) {
            throw new InvalidLink('Could not modify $fromDate while calculating $to date.');
        }

        return new static($title, $from, $to, true);
    }

    /** Set description of the Event. */
    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /** Set address of the Event. */
    public function address(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function formatWith(Generator $generator): string
    {
        return $generator->generate($this);
    }

    public function google(): string
    {
        return $this->formatWith(new Google());
    }

    /**
     * @psalm-param IcsOptions $options ICS specific properties and components
     * @param array<non-empty-string, non-empty-string> $options ICS specific properties and components
     * @param array{format?: \Spatie\CalendarLinks\Generators\Ics::FORMAT_*} $presentationOptions
     * @return string
     */
    public function ics(array $options = [], array $presentationOptions = []): string
    {
        return $this->formatWith(new Ics($options, $presentationOptions));
    }

    public function yahoo(): string
    {
        return $this->formatWith(new Yahoo());
    }

    public function webOutlook(): string
    {
        return $this->formatWith(new WebOutlook());
    }

    public function webOffice(): string
    {
        return $this->formatWith(new WebOffice());
    }
}
