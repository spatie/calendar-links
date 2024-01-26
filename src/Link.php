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
        $this->title = $title;
        $this->allDay = $allDay;

        if ($from > $to) {
            throw InvalidLink::negativeDateRange($from, $to);
        }

        $this->from = clone $from;
        $this->to = clone $to;
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
        // When creating all day events, we need to be in the UTC timezone as all day events are "floating" based on the user's timezone
        if ($allDay) {
            $startDate = new \DateTime($from->format('Y-m-d'), new \DateTimeZone('UTC'));
            $numberOfDays = $from->diff($to)->days + 1;

            return self::createAllDay($title, $startDate, $numberOfDays);
        }

        return new static($title, $from, $to, $allDay);
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
        // In cases where the from date is not UTC, make sure it's UTC, size all day events are floating and non UTC dates cause bugs in the generators
        if ($fromDate->getTimezone() !== new \DateTimeZone('UTC')) {
            $fromDate = \DateTime::createFromFormat('Y-m-d', $fromDate->format('Y-m-d'));
        }

        $from = (clone $fromDate)->modify('midnight');
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
