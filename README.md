# Generate add to calendar links for Google, iCal and other calendar systems

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spatie/calendar-links.svg?style=flat-square)](https://packagist.org/packages/spatie/calendar-links)
[![Total Downloads](https://img.shields.io/packagist/dt/spatie/calendar-links.svg?style=flat-square)](https://packagist.org/packages/spatie/calendar-links)
[![Run Tests](https://github.com/spatie/calendar-links/actions/workflows/run-tests.yml/badge.svg)](https://github.com/spatie/calendar-links/actions/workflows/run-tests.yml)
[![Quality Score](https://img.shields.io/scrutinizer/g/spatie/calendar-links.svg?style=flat-square)](https://scrutinizer-ci.com/g/spatie/calendar-links)
[![Type coverage](https://shepherd.dev/github/spatie/calendar-links/coverage.svg)](https://shepherd.dev/github/spatie/calendar-links)
[![Psalm level](https://shepherd.dev/github/spatie/calendar-links/level.svg)](https://shepherd.dev/github/spatie/calendar-links)


Using this package, you can generate links to add events to calendar systems. Here's a quick example:

```php
use Spatie\CalendarLinks\Link;

Link::create(
    'Birthday party',
    new DateTime('2027-03-15 10:00', new DateTimeZone('Europe/Brussels')),
    new DateTime('2027-03-15 17:00', new DateTimeZone('Europe/Brussels')),
)->google();
```

This will output:

```
https://calendar.google.com/calendar/render?action=TEMPLATE&dates=20270315T100000/20270315T170000&ctz=Europe/Brussels&text=Birthday+party
```

Give both dates an explicit timezone, as above. The `ctz` parameter is taken from the start date, so without one your links follow the `date.timezone` of whichever machine generated them.

Name a place (`Europe/Brussels`, or an alias of one such as `Japan` or `US/Pacific`) rather than an offset. Parsing an ISO 8601 string leaves you with a zone named `+01:00`, and neither an offset nor an abbreviation (`CEST`) gives a calendar service a place to resolve. The same goes for the IANA entries that stand for no place: the POSIX rule sets (`EST`, `MST7MDT`), the other spellings of UTC, and the whole `Etc/GMT±N` family, which Google rejects outright and whose sign runs the opposite way from the offset it names. Those events are written in UTC instead (`dates=20270315T090000Z/20270315T160000Z`, with no `ctz`, and a UTC `DTSTART` in the ics file), so they land at the right instant everywhere, but the calendar has no zone to follow when the daylight saving rules of that place change.

If you follow that link (and are authenticated with Google), you’ll see a screen to add the event to your calendar.

The package can also generate ics files that you can open in several email and calendar programs, including Microsoft Outlook, Google Calendar, and Apple Calendar.

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/calendar-links.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/calendar-links)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```sh
composer require spatie/calendar-links
```

## Usage

```php
<?php
use Spatie\CalendarLinks\Link;

$from = new DateTime('2027-03-15 10:00', new DateTimeZone('Europe/Brussels'));
$to = new DateTime('2027-03-15 17:00', new DateTimeZone('Europe/Brussels'));

$link = Link::create('Birthday party', $from, $to)
    ->description('A full day of hands-on testing. Bring a laptop.')
    ->address('Samberstraat 69D, 2060 Antwerp, Belgium');

// A link that creates the event on Google calendar
echo $link->google();

// A link that creates the event on Yahoo calendar
echo $link->yahoo();

// A link that creates the event on outlook.live.com
echo $link->webOutlook();

// A link that creates the event on outlook.cloud.microsoft
echo $link->webOffice();

// A data URI holding an ics file, for iCal, Apple Calendar and Outlook
echo $link->ics();

// The raw ics body instead of a data URI, to attach to an email as a file
echo $link->ics([], ['format' => 'file']);

// Any generator of your own
echo $link->formatWith(new \Your\Generator());
```

`google()` produces:

```
https://calendar.google.com/calendar/render?action=TEMPLATE&dates=20270315T100000/20270315T170000&ctz=Europe/Brussels&text=Birthday+party&details=A+full+day+of+hands-on+testing.+Bring+a+laptop.&location=Samberstraat+69D%2C+2060+Antwerp%2C+Belgium
```

and `ics([], ['format' => 'file'])` produces:

```
BEGIN:VCALENDAR
VERSION:2.0
PRODID:Spatie calendar-links
BEGIN:VEVENT
UID:dc01d073bb7e6c2bfae1bd5c43283062
SUMMARY:Birthday party
DTSTAMP:20270315T090000Z
DTSTART:20270315T090000Z
DTEND:20270315T160000Z
DESCRIPTION:A full day of hands-on testing. Bring a laptop.
LOCATION:Samberstraat 69D\, 2060 Antwerp\, Belgium
END:VEVENT
END:VCALENDAR
```

### Separate start and end timezones

Most events start and end in the same place. Some do not, and a flight is the obvious case. Pass a `DateTime` in each zone and the difference is kept:

```php
$flight = Link::create(
    'NH 106 Tokyo to Los Angeles',
    new DateTime('2027-03-15 09:00', new DateTimeZone('Asia/Tokyo')),
    new DateTime('2027-03-15 09:30', new DateTimeZone('America/Los_Angeles')),
);
```

Google names each end with `stz` and `etz`, which take priority over `ctz`, so `ctz` is left out:

```
https://calendar.google.com/calendar/render?action=TEMPLATE&dates=20270315T090000/20270315T093000&stz=Asia/Tokyo&etz=America/Los_Angeles&text=NH+106+Tokyo+to+Los+Angeles
```

and the ics file names them with `TZID`, instead of flattening both ends to UTC:

```
DTSTAMP:20270315T000000Z
DTSTART;TZID=Asia/Tokyo:20270315T090000
DTEND;TZID=America/Los_Angeles:20270315T093000
```

`DTSTAMP` stays in UTC, as RFC 5545 requires.

Nothing needs switching on. An event whose two ends share a zone is generated exactly as before, and so is an all-day event, which has no clock time to place in a zone. Yahoo has no timezone parameter and Outlook accepts only UTC or the viewer's own zone, so both keep their current output.

Both ends have to name a place for this. If either one does not, the pair is written in UTC together, since naming only one end would leave the other to be read in whichever zone the viewer sits in.

`$link->from` and `$link->to` are unchanged too: `$to` is still normalised into `$from`'s zone, so the two are directly comparable. The zones are recorded separately, on `$link->fromTimezone` and `$link->toTimezone`.

### Guests

Every generator can invite guests, with a required or optional role:

```php
$link->guest('freek@example.com')
    ->guests(['ruben@example.com', 'alex@example.com'])
    ->guest('willem@example.com', optional: true);
```

An address that is already on the list is ignored (compared without regard to case), so the first spelling and role you gave a guest are the ones that are kept.

Each service spells the same thing differently:

| Generator | Required | Optional |
| --- | --- | --- |
| Google | `add=` | `add=`, with an `_o` suffix on the address |
| WebOutlook / WebOffice | `to=` | `cc=` |
| Yahoo | `inv_list=` | `inv_list=` (Yahoo has no optional role, so optional guests are invited as required ones) |
| ICS | `ATTENDEE;ROLE=REQ-PARTICIPANT` | `ATTENDEE;ROLE=OPT-PARTICIPANT` |

So the ics file above gains:

```
ATTENDEE;ROLE=REQ-PARTICIPANT:mailto:freek@example.com
ATTENDEE;ROLE=REQ-PARTICIPANT:mailto:ruben@example.com
ATTENDEE;ROLE=REQ-PARTICIPANT:mailto:alex@example.com
ATTENDEE;ROLE=OPT-PARTICIPANT:mailto:willem@example.com
```

Two things to keep in mind. Only plain email addresses are accepted, so a display name (the `Name <email>` form) throws an `InvalidLink` exception, because Yahoo cannot represent one. And guests are emitted in addition to any custom URL parameters, so if you also pass `add`, `to`, `cc` or `inv_list` by hand, the parameter appears twice and the service decides which one wins.

### Yahoo parameters

`yahoo()` takes an array of extra query parameters:

```php
echo $link->yahoo(['TYPE' => 7]);
```

`TYPE` picks the event charm, as a zero based index into Yahoo's list of 17 charms (`0` is General, `7` is Birthday, `13` is Phone).

Yahoo has no timezone parameter, so a timed event is sent as floating local time: an event created at 09:00 is composed as 09:00, whatever the reader's timezone.

A multi-day all-day event is composed as a timed event that runs from midnight on the first day to midnight on the last one. The dates and the duration are right, only the all-day banner is missing. `DUR=allday` is the parameter that flips the all-day toggle, but Yahoo ignores `DUR` as soon as `ET` is present, and `DUR` has no multi-day all-day value, so a multi-day event has to send `ET` to keep its end date. Single day all-day events are unaffected, since they need no `ET` and are sent as `ST=<date>&DUR=allday`.

### ICS options

`ics()` takes an array of properties that are written into the file as RFC 5545 values:

```php
echo $link->ics([
    'UID' => 'workshop-2027-03-15',
    'URL' => 'https://example.com/events/birthday-party',
    'RRULE' => 'FREQ=WEEKLY;BYDAY=MO,WE,FR',
    'TRANSP' => 'TRANSPARENT',
    'CLASS' => 'PRIVATE',
    'REMINDER' => ['DESCRIPTION' => 'The workshop starts in 15 minutes'],
]);
```

| Option | What it does | Specification |
| --- | --- | --- |
| `UID` | Identifies the event, so a later file carrying the same value updates it instead of creating a second one | [section 3.8.4.7](https://datatracker.ietf.org/doc/html/rfc5545#section-3.8.4.7) |
| `URL` | Points at a page about the event | [section 3.8.4.6](https://datatracker.ietf.org/doc/html/rfc5545#section-3.8.4.6) |
| `PRODID` | Names the product that wrote the file. Defaults to `Spatie calendar-links` | [section 3.7.3](https://datatracker.ietf.org/doc/html/rfc5545#section-3.7.3) |
| `DTSTAMP` | When the event information was last revised. Takes a `DateTimeInterface` and is written in UTC. Defaults to the event start | [section 3.8.7.2](https://datatracker.ietf.org/doc/html/rfc5545#section-3.8.7.2) |
| `REMINDER` | Adds an alarm. Pass `[]` for the default of 15 minutes before, or set `DESCRIPTION` and `TIME` | [section 3.6.6](https://datatracker.ietf.org/doc/html/rfc5545#section-3.6.6) |
| `RRULE` | Repeats the event | [section 3.8.5.3](https://datatracker.ietf.org/doc/html/rfc5545#section-3.8.5.3) |
| `TRANSP` | `OPAQUE` shows the time as busy, `TRANSPARENT` shows it as free | [section 3.8.2.7](https://datatracker.ietf.org/doc/html/rfc5545#section-3.8.2.7) |
| `CLASS` | Visibility: `PUBLIC`, `PRIVATE` or `CONFIDENTIAL` | [section 3.8.1.3](https://datatracker.ietf.org/doc/html/rfc5545#section-3.8.1.3) |
| `X-MICROSOFT-CDO-BUSYSTATUS` | `FREE`, `TENTATIVE`, `BUSY` or `OOF`. `TRANSP` only tells busy from free, so Outlook needs this one to show tentative or out of office | MS-OXCICAL |

`DTSTAMP` defaults to the event start rather than the moment the file is written, so the same `Link` always produces the same bytes and the output stays cacheable. The cost is that a client cannot read it as a revision signal when the same `UID` is imported twice. Pass your own value when that matters:

```php
echo $link->ics([
    'UID' => 'workshop-2027-03-15',
    'DTSTAMP' => new DateTimeImmutable('now'),
]);
```

It has to be a `DateTimeInterface`, so a date string throws an `InvalidLink` rather than being quietly ignored. The value is written as a UTC date-time, for all-day events too, since RFC 5545 does not allow a bare date here.

`URL` and `RRULE` are written into the file as they are given, so they must not contain a carriage return or a line feed. `TRANSP`, `CLASS` and `X-MICROSOFT-CDO-BUSYSTATUS` take one of the tokens listed above in any case, and are written upper-cased. `CLASS` deliberately accepts only those three, not the `x-name` and `iana-token` values RFC 5545 also permits, since validating that grammar costs more than it buys. A value that breaks either rule throws a `Spatie\CalendarLinks\Exceptions\InvalidLink`, so route user supplied data through the event's own fields (the title argument of `Link::create()` and `Link::createAllDay()`, `description()` and `address()`), which are escaped for you, rather than through these options.

A second argument controls presentation rather than content:

```php
echo $link->ics([], ['format' => 'file']); // the raw ics body, rather than a data URI
```

### Extending a generator

The generators cover the options that at least two services share. Everything else is deliberately left to subclassing: `Ics`, `Google` and `Yahoo` are not final, the two Outlook generators share the extendable `BaseOutlook` base class, and the escaping helpers (`Ics::escapeString()`, `Yahoo::sanitizeText()`, `BaseOutlook::sanitizeString()`) are `protected`, so a service specific property is a small subclass rather than a fork. The ICS generator has two hook methods for exactly that:

```php
use Spatie\CalendarLinks\Generators\Ics;
use Spatie\CalendarLinks\Link;

class MyIcs extends Ics
{
    /** @return list<string> */
    #[\Override]
    protected function additionalEventProperties(Link $link): array
    {
        return [
            'CATEGORIES:'.$this->escapeString('Workshops'),
            'STATUS:CONFIRMED',
        ];
    }
}

echo $link->formatWith(new MyIcs());
```

`additionalCalendarProperties()` does the same at the `VCALENDAR` level (for example `X-WR-CALNAME`). `Google` and `Yahoo` can be pointed at another endpoint by redefining their `BASE_URL` constant; for Outlook, extend `BaseOutlook` and implement `baseUrl()`. A completely custom generator only needs to implement the `Generator` interface.

## Package principles

1. it should produce a small output (to keep page-size small)
2. it should be fast (no any external heavy dependencies)
3. all `Link` class features should be supported by at least 2 generators (different services have different features)

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information.

## Testing

```sh
composer test
```

## Contributing

Please see [CONTRIBUTING](https://github.com/spatie/.github/blob/main/CONTRIBUTING.md) for details.

## Security

If you've found a bug regarding security, please mail [security@spatie.be](mailto:security@spatie.be) instead of using the issue tracker.

## Postcardware

You're free to use this package (it's [MIT-licensed](LICENSE.md)), but if it makes it to your production environment, we highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using.

Our address is: Spatie, Samberstraat 69D, 2060 Antwerp, Belgium.

We publish all received postcards [on our company website](https://spatie.be/en/opensource/postcards).
## Credits

- [Sebastian De Deyne](https://github.com/sebastiandedeyne)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
