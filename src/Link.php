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
     * @throws \Spatie\CalendarLinks\Exceptions\InvalidLink When date range is invalid.
     */
    public static function create(string $title, \DateTimeInterface $from, \DateTimeInterface $to)
    {
        return new static($title, $from, $to);
    }

    /**
     * @param positive-int $numberOfDays
     * @throws \Spatie\CalendarLinks\Exceptions\InvalidLink When date range is invalid.
     */
    public static function createAllDay(string $title, \DateTimeInterface $from, int $numberOfDays = 1)
    {
        $to = (clone $from)->modify("+$numberOfDays days");
        assert($to instanceof \DateTimeInterface);

        return new static($title, $from, $to, true);
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

    public function ics(array $options = []): string
    {
        return $this->formatWith(new Ics($options));
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
