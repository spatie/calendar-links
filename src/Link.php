<?php

namespace Spatie\CalendarLinks;

use DateTime;
use Spatie\CalendarLinks\Generators\Ics;
use Spatie\CalendarLinks\Generators\Yahoo;
use Spatie\CalendarLinks\Generators\Google;
use Spatie\CalendarLinks\Exceptions\InvalidLink;

/**
 * @property string $title
 * @property \DateTime $from
 * @property \DateTime $to
 * @property string $description
 * @property string $address
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

    public function __construct(string $title, DateTime $from, DateTime $to, bool $allDay = false)
    {
        $this->title = $title;
        $this->allDay = $allDay;

        if ($to < $from) {
            throw InvalidLink::invalidDateRange($from, $to);
        }

        $this->from = $from->format('Ymd\THis');
        $this->to = $to->format('Ymd\THis');

        if ($this->allDay) {
            $this->from = $from->format('Ymd');
            $this->to = $to->format('Ymd');
        }
    }

    /**
     * @param string $title
     * @param \DateTime $from
     * @param \DateTime $to
     * @param bool $allDay
     *
     * @return static
     * @throws InvalidLink
     */
    public static function create(string $title, DateTime $from, DateTime $to, bool $allDay = false)
    {
        return new static($title, $from, $to, $allDay);
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

    public function google(): string
    {
        return (new Google())->generate($this);
    }

    public function ics(): string
    {
        return (new Ics())->generate($this);
    }

    public function yahoo(): string
    {
        return (new Yahoo())->generate($this);
    }

    public function __get($property)
    {
        return $this->$property;
    }
}
