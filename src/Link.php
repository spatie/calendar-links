<?php

namespace Spatie\CalendarLinks;

use DateTime;
use Spatie\CalendarLinks\Exceptions\InvalidLink;
use Spatie\CalendarLinks\Generators\Ics;
use Spatie\CalendarLinks\Generators\Yahoo;
use Spatie\CalendarLinks\Generators\Google;

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

    /** @var string */
    protected $address;

    public function __construct(string $title, DateTime $from, DateTime $to)
    {
        $this->title = $title;

        if ($to < $from) {
            throw InvalidLink::invalidDateRange($from, $to);
        }

        $this->from = $from;
        $this->to = $to;
    }

    /**
     * @param string $title
     * @param \DateTime $from
     * @param \DateTime $to
     *
     * @return static
     */
    public static function create(string $title, DateTime $from, DateTime $to)
    {
        return new static($title, $from, $to);
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
