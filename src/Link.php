<?php

namespace Spatie\CalendarLinks;

use Spatie\CalendarLinks\Exceptions\InvalidLink;
use Spatie\CalendarLinks\Generators\Google;
use Spatie\CalendarLinks\Generators\Ics;
use Spatie\CalendarLinks\Generators\WebOffice;
use Spatie\CalendarLinks\Generators\WebOutlook;
use Spatie\CalendarLinks\Generators\Yahoo;

/**
 * @property-read string $title
 * @property-read \DateTimeInterface|\DateTime|\DateTimeImmutable $from
 * @property-read \DateTimeInterface|\DateTime|\DateTimeImmutable $to
 * @property-read string $description
 * @property-read string $address
 * @property-read bool $allDay
 * @psalm-import-type IcsOptions from \Spatie\CalendarLinks\Generators\Ics
 * @psalm-import-type GoogleUrlParameters from \Spatie\CalendarLinks\Generators\Google
 * @psalm-import-type YahooUrlParameters from \Spatie\CalendarLinks\Generators\Yahoo
 * @psalm-import-type OutlookUrlParameters from \Spatie\CalendarLinks\Generators\BaseOutlook
 */
class Link
{
    /** @var string */
    protected $title;

    /** @var \DateTime */
    protected $from;

    /** @var \DateTime */
    protected $to;

    /** @var string */
    protected $description;

    /** @var bool */
    protected $allDay;

    /** @var string */
    protected $address;

    public function __construct(string $title, \DateTimeInterface $from, \DateTimeInterface $to, bool $allDay = false)
    {
        $this->from = clone $from;
        $this->to = clone $to;
        $this->title = $title;
        $this->allDay = $allDay;

        // Ensures timezones match.
        if ($this->from->getTimezone()->getName() !== $this->to->getTimezone()->getName()) {
            $this->to->setTimezone($from->getTimezone());
        }

        // Ensures from date is earlier than to date.
        if ($this->from > $this->to) {
            throw InvalidLink::negativeDateRange($from, $to);
        }
    }

    /**
     * @param string $title
     * @param \DateTimeInterface $from
     * @param \DateTimeInterface $to
     * @param bool $allDay
     *
     * @return static
     * @throws InvalidLink
     */
    public static function create(string $title, \DateTimeInterface $from, \DateTimeInterface $to, bool $allDay = false)
    {
        $from_date = clone $from;
        $to_date = clone $to;

        // If all day, we need to add 1 day to end date to get the correct duration.
        if ($allDay) {
            $to_date->modify('+1 day');
        }

        return new static($title, $from_date, $to_date, $allDay);
    }

    /**
     * @param string $title
     * @param \DateTimeInterface|\DateTime|\DateTimeImmutable $fromDate
     * @param int $numberOfDays
     *
     * @return Link
     * @throws InvalidLink
     */
    public static function createAllDay(string $title, \DateTimeInterface $fromDate, int $numberOfDays = 1): self
    {
        $from = (clone $fromDate);
        $to = (clone $from)->modify("+$numberOfDays days");

        return new self($title, $from, $to, true);
    }

    /**
     * @param string $description
     *
     * @return $this
     */
    public function description(string $description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @param string $address
     *
     * @return $this
     */
    public function address(string $address)
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
     * @param array<non-empty-string, non-empty-string> $options ICS specific properties and components
     * @param array{format?: \Spatie\CalendarLinks\Generators\Ics::FORMAT_*} $presentationOptions
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

    public function __get($property)
    {
        return $this->$property;
    }
}
