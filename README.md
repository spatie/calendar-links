# Generate add to calendar links for Google, iCal and other calendar systems

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spatie/calendar-links.svg?style=flat-square)](https://packagist.org/packages/spatie/calendar-links)
[![Total Downloads](https://img.shields.io/packagist/dt/spatie/calendar-links.svg?style=flat-square)](https://packagist.org/packages/spatie/calendar-links)
[![Test](https://github.com/spatie/calendar-links/workflows/Test/badge.svg)](https://github.com/spatie/calendar-links/actions/workflows/run-tests.yml)
[![Quality Score](https://img.shields.io/scrutinizer/g/spatie/calendar-links.svg?style=flat-square)](https://scrutinizer-ci.com/g/spatie/calendar-links)
[![Type coverage](https://shepherd.dev/github/spatie/calendar-links/coverage.svg)](https://shepherd.dev/github/spatie/calendar-links)
[![Psalm level](https://shepherd.dev/github/spatie/calendar-links/level.svg)](https://shepherd.dev/github/spatie/calendar-links)


Using this package, you can generate links to add events to calendar systems. Here's a quick example:

```php
use Spatie\CalendarLinks\Link;

Link::create(
    'Birthday',
    DateTime::createFromFormat('Y-m-d H:i', '2018-02-01 09:00'),
    DateTime::createFromFormat('Y-m-d H:i', '2018-02-01 18:00')
)->google();
```

This will output: `https://calendar.google.com/calendar/render?action=TEMPLATE&text=Birthday&dates=20180201T090000/20180201T180000&sprop=&sprop=name:`

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

$from = DateTime::createFromFormat('Y-m-d H:i', '2018-02-01 09:00');
$to = DateTime::createFromFormat('Y-m-d H:i', '2018-02-01 18:00');

$link = Link::create('Sebastian’s birthday', $from, $to)
    ->description('Cookies & cocktails!')
    ->address('Kruikstraat 22, 2018 Antwerpen');

// Generate a link to create an event on Google calendar
echo $link->google();

// Generate a link to create an event on Yahoo calendar
echo $link->yahoo();

// Generate a link to create an event on outlook.live.com calendar
echo $link->webOutlook();

// Generate a link to create an event on outlook.office.com calendar
echo $link->webOffice();

// Generate a data URI for an ics file (for iCal & Outlook)
echo $link->ics();
echo $link->ics(['UID' => 'custom-id']); // Custom UID (to update existing events)
echo $link->ics(['URL' => 'https://my-page.com']); // Custom URL
echo $link->ics(['REMINDER' => []]); // Add the default reminder (for iCal & Outlook)
echo $link->ics(['REMINDER' => ['DESCRIPTION' => 'Remind me', 'TIME' => new \DateTime('tomorrow 12:30 UTC')]]); // Add a custom reminder
echo $link->ics([], ['format' => 'file']); // use file output; e.g. to attach ics as a file to an email.

// Generate a data URI using arbitrary generator:
echo $link->formatWith(new \Your\Generator());
```

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
