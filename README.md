# Generate add to calendar links for Google, iCal and other calendar systems

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spatie/calendar-links.svg?style=flat-square)](https://packagist.org/packages/spatie/calendar-links)
![GitHub Workflow Status](https://img.shields.io/github/workflow/status/spatie/calendar-links/CI/master?style=flat-square)
[![Quality Score](https://img.shields.io/scrutinizer/g/spatie/calendar-links.svg?style=flat-square)](https://scrutinizer-ci.com/g/spatie/calendar-links)
[![StyleCI](https://styleci.io/repos/103638080/shield?branch=master)](https://styleci.io/repos/103638080)
[![Total Downloads](https://img.shields.io/packagist/dt/spatie/calendar-links.svg?style=flat-square)](https://packagist.org/packages/spatie/calendar-links)

Using this package you can generate links to add events to calendar systems. Here's a quick example:

```php
use Spatie\CalendarLinks\Link;

Link::create(
    'Birthday',
    DateTime::createFromFormat('Y-m-d H:i', '2018-02-01 09:00'),
    DateTime::createFromFormat('Y-m-d H:i', '2018-02-01 18:00')
)->google();
```

This will output: `https://calendar.google.com/calendar/render?action=TEMPLATE&text=Birthday&dates=20180201T090000/20180201T180000&sprop=&sprop=name:`

If you follow that link (and are authenticated with Google) you'll see a screen to add the event to your calendar.

The package can also generate ics files that you can open in several email and calendar programs, including Microsoft Outlook, Google Calendar, and Apple Calendar.

## Support us

Learn how to create a package like this one, by watching our premium video course:

[![Laravel Package training](https://spatie.be/github/package-training.jpg)](https://laravelpackage.training)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require spatie/calendar-links
```

## Usage

``` php
<?php
use Spatie\CalendarLinks\Link;

$from = DateTime::createFromFormat('Y-m-d H:i', '2018-02-01 09:00');
$to = DateTime::createFromFormat('Y-m-d H:i', '2018-02-01 18:00');

$link = Link::create('Sebastian\'s birthday', $from, $to)
    ->description('Cookies & cocktails!')
    ->address('Kruikstraat 22, 2018 Antwerpen');

// Generate a link to create an event on Google calendar
echo $link->google();

// Generate a link to create an event on Yahoo calendar
echo $link->yahoo();

// Generate a link to create an event on outlook.com calendar
echo $link->webOutlook();

// Generate a link to create an event on outlook.office.com calendar
echo $link->office365();

// Generate a data uri for an ics file (for iCal & Outlook)
echo $link->ics();

// Generate a data uri using arbitrary generator:
echo $link->formatWith(new \Your\Generator());
```

> ⚠️ ICS download links don't work in IE and EdgeHTML-based Edge browsers, see [details](https://github.com/spatie/calendar-links/issues/71).

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Testing

``` bash
composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email freek@spatie.be instead of using the issue tracker.

## Postcardware

You're free to use this package (it's [MIT-licensed](LICENSE.md)), but if it makes it to your production environment we highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using.

Our address is: Spatie, Samberstraat 69D, 2060 Antwerp, Belgium.

We publish all received postcards [on our company website](https://spatie.be/en/opensource/postcards).
## Credits

- [Sebastian De Deyne](https://github.com/sebastiandedeyne)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
