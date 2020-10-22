<?php

namespace Spatie\CalendarLinks;

use DateTimeInterface;
use Spatie\CalendarLinks\Exceptions\InvalidLink;
use Spatie\CalendarLinks\Generators\Google;
use Spatie\CalendarLinks\Generators\Ics;
use Spatie\CalendarLinks\Generators\WebOutlook;
use Spatie\CalendarLinks\Generators\Yahoo;

/**
 * @property-read string $title
 * @property-read DateTimeInterface|\DateTime|\DateTimeImmutable $from
 * @property-read DateTimeInterface|\DateTime|\DateTimeImmutable $to
 * @property-read string $description
 * @property-read string $address
 * @property-read bool $allDay
 * @property-read string $url
 * @property-read string $urlTitle
 * @property-read string $descriptionWithUrlFormat
 * @property-read string $descriptionWithUrl
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

    /** @var string */
    protected $url;

    /** @var string */
    protected $urlTitle;

    /** @var string format  {description}, {url}, {urlTitle} */
    protected $descriptionWithUrlFormat = '{url} {description}';

    public function __construct(string $title, DateTimeInterface $from, DateTimeInterface $to, bool $allDay = false)
    {
        $this->title = $title;
        $this->allDay = $allDay;

        if ($to < $from) {
            throw InvalidLink::invalidDateRange($from, $to);
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
    public static function create(string $title, DateTimeInterface $from, DateTimeInterface $to, bool $allDay = false)
    {
        return new static($title, $from, $to, $allDay);
    }

    /**
     * @param string $title
     * @param DateTimeInterface|\DateTime|\DateTimeImmutable $fromDate
     * @param int $numberOfDays
     *
     * @return Link
     * @throws InvalidLink
     */
    public static function createAllDay(string $title, DateTimeInterface $fromDate, int $numberOfDays = 1): self
    {
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

    /**
     * @param string $url
     *
     * @return $this
     */
    public function url(string $url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * @param string $urlTitle
     *
     * @return $this
     */
    public function urlTitle(string $urlTitle)
    {
        $this->urlTitle = $urlTitle;

        return $this;
    }

    public function getUrlTitle(): string
    {
        return $this->urlTitle ?? preg_replace('/^(https?:\/\/|mailto:)/', '', $this->url);
    }

    /**
     * @param string $descriptionWithUrlFormat format string with the following placeholders
     *                                         {description}, {url}, {urlTitle}
     *
     * @return $descriptionWithUrlFormat
     */
    public function descriptionWithUrlFormat(string $format)
    {
        $this->descriptionWithUrlFormat = $format;

        return $this;
    }

    /**
     * @param string|null $format overrides $this->descriptionWithUrlFormat
     *                            {description}, {url}, {urlTitle}
     *                            if null, $this->descriptionWithUrlFormat is used
     *
     * @return string description formated with url or one if the other is empty.
     */
    public function formatDescriptionWithUrl(?string $format = null): string
    {
        if (empty($this->url))
            return $this->description;

        if (empty($this->description))
            return $this->url;

        return strtr($format ?? $this->descriptionWithUrlFormat, [
            '{description}' => $this->description,
            '{url}' => $this->url,
            '{urlTitle}' => $this->getUrlTitle(),
        ]);
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

    public function __get($property)
    {
        switch ($property) {
            case 'descriptionWithUrl':
                return $this->formatDescriptionWithUrl();
            case 'urlTitle':
                return $this->getUrlTitle();
            default:
                return $this->$property;
        }
    }
}
