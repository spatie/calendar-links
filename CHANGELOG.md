# Changelog

All notable changes to `calendar-links` will be documented in this file

## 1.4.0 - 2020-05-2
### Added
- Allow specifying custom `uid` ICS links (https://github.com/spatie/calendar-links/pull/85)
- Support PHP 8.0

### Changed
- Require PHP 7.2+

## 1.3.0 - 2020-04-29
- Support custom generators (`$link->formatWith(new Your\Generator()`)
- Fix iCal links that contains special chars (use base64 for encoding)
- Fix Outlook links: use new base URI and datetime formats
- Fix Yahoo links: events had invalid end datetime (due to a bug on Yahoo side)

## 1.2.4 - 2019-07-17
- Fix Google links for all-day events (use next day as end-date for single-day events)
- Fix Outlook links for all-day events (omit `enddt` for single-day events)
- Add a new `Link::createAllDay` static constructor to simplify creating of all-day events

## 1.2.3 - 2019-02-14
- Fix iCal all day links (use DURATION according RFC 5545)

## 1.2.2 - 2019-01-15
- Fix Yahoo links for multiple days events

## 1.2.1 - 2019-01-13
- Fix iCal: Use CRLF instead of LF (according RFC 5545)
- Fix iCal: Specify UID property (according RFC 5545)
- Fix iCal: Escape `;` character (according RFC 5545)
- Fix iCal: Remove empty new line from .ics files

## 1.2.0 - 2019-01-10
- Support timezones
- Add outlook.com link generator

## 1.1.1 - 2018-10-08
- Fix Yahoo links

## 1.1.0 - 2018-08-13
- Add all day support

## 1.0.3 - 2018-07-23
- Fix newlines in description

## 1.0.2 - 2018-05-15
- Fix for iCal links in Safari

## 1.0.1 - 2018-04-30
- Use `\n` instead of `%0A` when generating an ics file

## 1.0.0 - 2017-09-29

- initial release
