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
 * @psalm-import-type IcsOptions from \Spatie\CalendarLinks\Generators\Ics
 * @psalm-import-type GoogleUrlParameters from \Spatie\CalendarLinks\Generators\Google
 * @psalm-import-type YahooUrlParameters from \Spatie\CalendarLinks\Generators\Yahoo
 * @psalm-import-type OutlookUrlParameters from \Spatie\CalendarLinks\Generators\BaseOutlook
 * @psalm-import-type IcsPresentationOptions from \Spatie\CalendarLinks\Generators\Ics
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

        // Ensures timezones match.
        if ($from->getTimezone()->getName() !== $to->getTimezone()->getName()) {
            $to = (clone $to)->setTimezone($from->getTimezone());
        }

        $this->from = \DateTimeImmutable::createFromInterface($from);
        $this->to = \DateTimeImmutable::createFromInterface($to);

        // Ensures from date is earlier than to date.
        if ($this->from > $this->to) {
            throw InvalidLink::negativeDateRange($this->from, $this->to);
        }
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
        $to = (clone $from)->modify("+$numberOfDays days");
        assert($to instanceof \DateTimeInterface);

        return new static($title, $from, $to, true);
    }

    /** Set description of the Event. */
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
