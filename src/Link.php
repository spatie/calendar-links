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

        // Ensures from date is earlier than to date.
        if ($this->from > $this->to) {
            throw InvalidLink::negativeDateRange($from, $to);
        }

        // Ensures timezones match.
        if ($this->from->getTimezone()->getName() !== $this->to->getTimezone()->getName()) {
            $this->to->setTimezone($from->getTimezone());
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

    public function __get($property)
    {
        return $this->$property;
    }
}
