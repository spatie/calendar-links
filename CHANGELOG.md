# Changelog

All notable changes to `calendar-links` will be documented in this file

## 2.1.0 - 2026-08-20

### Added
- Add first-class guests (attendees) via `Link::guest()` and `Link::guests()`, with required and optional roles. Guests are rendered as `add` for Google, `inv_list` for Yahoo, `to` and `cc` for Outlook, and `ATTENDEE` properties in ICS (#229)
- Support separate start and end timezones (useful for flights): new `Link::$fromTimezone`, `Link::$toTimezone` and `Link::hasDistinctTimezones()`. Google links use `stz` and `etz`, ICS files write each endpoint with its own `TZID` alongside a matching `VTIMEZONE` component, as RFC 5545 requires. Two spellings of one zone (`UTC` and `Etc/UTC`, or the bare `Z` that PHP leaves on a `Z` suffixed ISO string) count as a single zone, so only an event that genuinely crosses zones takes this path (#233, #246, #249)
- ICS: support `TRANSP`, `CLASS`, `RRULE` and `X-MICROSOFT-CDO-BUSYSTATUS` via the options array (#230)
- ICS: support a custom `DTSTAMP` via the options array. It defaults to the event start, which keeps the same `Link` producing the same bytes, so pass your own value when you need it read as a revision signal (#244)
- Support repeated URL parameters in URL generators: an array value in `$urlParameters` is rendered as a repeated query parameter (#228)
- ICS: subclasses can append extra properties through the new `additionalCalendarProperties()` and `additionalEventProperties()` extension points, emitted before the nested `VALARM` component so the output stays valid (#242, #247)

### Changed
- Generators are easier to extend: `Ics::generateAlertComponent()`, `Yahoo::sanitizeText()`, `Yahoo::sanitizeAddressList()` and `BaseOutlook::sanitizeString()` are now `protected`, and `Google`/`Yahoo` read `BASE_URL` late statically so a subclass can redefine it
- ICS: a recurring event in a timezone that moves its clocks is now written as a local time named by a `TZID`, with a matching `VTIMEZONE`, where it used to be written as a UTC instant. An `RRULE` repeats the local time of its `DTSTART`, so a start pinned to UTC put every occurrence past a daylight saving change an hour away from the time the event was booked for. A recurring event in UTC, in a zone that keeps one offset the year round, or in a zone that names no place is unaffected, and so is any event without an `RRULE`
- ICS: `REMINDER` now has to be an array, its `TIME` a `DateTimeInterface` and its `DESCRIPTION` a string. A value of another type used to be dropped for the default alarm without a word, which handed back a reminder nobody asked for
- ICS: the presentation `format` now has to be `html` or `file`. Anything else used to fall through to the data URI, so `['format' => 'FILE']` silently returned a link rather than the file it asked for
- URL generators render a `false` parameter value as a bare flag (`&key`) rather than as an empty assignment (`&key=`)

### Fixed
- `Link::guest()` and `Link::guests()` now ignore an address that is already on the guest list, compared without regard to case, so nobody is invited twice
- ICS: an `ATTENDEE` address can no longer inject calendar properties. `guest()` rejects a control character, but `Link::$guests` is a public property that can be assigned around it, and a CR or an LF in an address ended the `ATTENDEE` property and started another. Control characters are now percent encoded into the `mailto` URI
- ICS: `URL` and `RRULE` now reject every control character rather than only CR and LF. Neither a URI nor a recurrence rule admits one, and writing it out produced a file no parser accepts
- ICS: the `VTIMEZONE` components now carry a yearly recurrence rule on the last change of each kind, whenever the zone repeats that change on a fixed weekday of a fixed month. Without one the component stopped at the year it described, and a recurring event past that resolved against the wrong offset for part of every later year on a client that does not resolve bare IANA names
- A description or address of `'0'` is no longer dropped from the output as if it were empty
- The exception for a negative date range no longer claims TO must be *greater than* FROM, since equal instants are accepted, and it now prints each time with its timezone
- All-day events whose start and end dates carry different timezones no longer gain or lose a day. The end date is now read as the calendar date it was written as, instead of being converted as an instant
- Timezones that name no place (an offset such as `+02:00`, an abbreviation such as `CEST`, the POSIX rule sets, and the whole `Etc/` tree) are written in UTC instead of being named. Google silently ignores a `ctz` it cannot resolve, which left the event at the wrong instant for every viewer outside that offset, and an offset in an ICS `TZID` cut the property value in half at its colon. Parsing an ISO 8601 string with an offset is the everyday way to reach this (#248)
- ICS: values passed through the options array can no longer inject calendar properties. `UID` and `PRODID` are escaped as the TEXT values they are, `URL` and `RRULE` reject a carriage return or line feed, the enumerated properties are checked against their allowed tokens, and a custom `REMINDER.DESCRIPTION` is escaped like the default one (#245)
- ICS: close several RFC 5545 gaps. Content lines longer than 75 octets are folded (without splitting a multibyte character), the file ends with a CRLF, the data URI declares `charset=utf-8` rather than the unregistered `utf8`, and control characters invalid in a TEXT value are dropped (#250)
- Yahoo: align deep link parameters with the service parser. The base URL drops the stale `view=d&type=20` pair, timed events send wall clock `ST` and `ET` values instead of UTC, single day all-day events send `DUR=allday`, and multi day all-day events send an exclusive `ET` end date (#232)
- ICS: correct attendee escaping (a `CAL-ADDRESS` is a URI, not TEXT) and reject guest email addresses that no calendar service can carry (#229)

### Docs
- Rework the README examples and fix the stale Google output (#231)
- Document that Yahoo composes a multi day all-day event as a timed one running from midnight to midnight, since `DUR=allday` and an `ET` end date cannot be sent together (#243)

### Notes for subclasses
The public API is unchanged, but two of the changes above are visible to a class extending a generator, and either can stop it loading:

- `Ics::$presentationOptions` is now declared `array`, where 2.0.x left it untyped. A subclass that redeclares the property without a type raises `Type of Sub::$presentationOptions must be array`. Drop the redeclaration, or type it `array` to match
- `Ics::generateAlertComponent()`, `Yahoo::sanitizeText()`, `Yahoo::sanitizeAddressList()` and `BaseOutlook::sanitizeString()` went from `private` to `protected`. A subclass that happens to declare a `private` method of the same name raises `Access level to Sub::sanitizeText() must be protected or weaker`. Widen it to `protected`, which is what it now overrides
- `Yahoo::BASE_URL` no longer carries the stale `view=d&type=20` pair. A subclass that built its own URL from the constant gets the shorter value

## 2.0.0 - 2026-02-10

### Breaking Changes
- Require PHP 8.3+ (previously 8.1+)
- `Link::$from` and `Link::$to` are now `DateTimeImmutable` (previously `DateTime`)
- `Link::$title`, `$from`, `$to`, and `$allDay` are now `public readonly`; `$description` and `$address` are `public`. The magic `__get()` method has been removed.
- Remove `$allDay` parameter from `Link::create()` — use `Link::createAllDay()` instead
- `Link::createAllDay()` end date is now inclusive (the constructor internally adds +1 day for calendar services)
- `Link` constructor is now `final`
- Remove deprecated `InvalidLink::invalidDateRange()` — use `InvalidLink::negativeDateRange()` instead
- `BaseOutlook::baseUrl()` visibility changed from `public` to `protected`
- `WebOffice` and `WebOutlook` classes are now `final`
- Date/time format properties in generators replaced with `private const` (prevents override via subclassing)
- Require PHPUnit 12+ / 13

### Added
- ICS: Allow custom `PRODID` via options array
- `Link::createAllDay()` now returns `static` instead of `self`

### Fixed
- Fix all-day events created via constructor being 1 day short
- ICS: `DTSTAMP` now always uses UTC datetime for all-day events (RFC 5545 compliance)
- ICS: Add `VALUE=DATE` parameter to `DTSTART` for all-day events (RFC 5545 compliance)
- ICS: `escapeString()` now properly handles backslashes, semicolons, commas, and newlines per RFC 5545 section 3.3.11
- ICS: Fix VALARM trigger not working when a specific date was set

### Internal
- Add `declare(strict_types=1)` to all source files
- Use PHP 8.3 typed class constants and `#[\Override]` attributes
- Use `DateTimeImmutable` throughout instead of mutable `DateTime`
- Replace `@test` annotations with PHPUnit `#[Test]` attributes
- Apply PSR-12 coding style
- Upgrade Psalm to v6 with improved type annotations

## 1.11.1 - 2024-03-07

## 1.11.0 - 2024-02-20

## 1.8.2 - 2022-12-11
### Changed
 - ICS: Use `DESCRIPTION` instead of `X-ALT-DESC` (as it has better support) by @cdubz in #158
 - Chore: fix tests, fix and improve CI

## 1.8.1 - 2022-12-01
### Changed
 - Remove PHP 7.4 support
 - Update dependencies

## 1.8.0 - 2022-08-20
### Changed
 - ICS: Add `PRODID` and `DTSTAMP` required parameters to make ICS valid by @makbeta
 - ICS: Fix HTML description for Outlook 2016 by @karthikbodu
 - Outlook: extract common logic for `WebOffice` and `WebOutlook` into a parent class by @lptn

### Fixed
- Simplify format of test snapshots: do not use base64 by @lptn
- Fix typo in README by @fabpot

## 1.7.2 - 2022-06-09
### Fixed
 - Outlook: Fixed #148 Support HTML-formatted description by @dravenk in #150

## 1.7.1 - 2022-02-14
### Changed
 - Outlook: Fixed location field characters (by @dravenk in #144)
 - Add missing dependency of php-cs-fixer and update it

## 1.7.0 - 2022-02-13
### Changed
 - New: Add support for outlook.office.com $link->webOffice(); (@dravenk and @gulios)
 - Google: Add timezone name if it is specified in both `from` and `to` dates and is the same for both (@bradyemerson)

## 1.6.0 - 2021-04-22
### Changed
- Drop support for PHP 7.2 and PHP 7.3

## 1.5.0 - 2021-04-22
### Changed
 - ICS: support URLs as option (@gulios)
 - ICS: support all day events spanning multiple days (@mrshowerman)

## 1.4.4 - 2021-04-13
### Fixed
 - Yahoo link doesn’t work (yahoo changed param names) (@mukeshsah08).
 - Exception message on invalid dates range (idea by @jason-nabooki)

## 1.4.3 - 2021-03-05
### Changed
 - Google: use UTC timezone to bypass problems with some timezone names unsupported by Google calendar (⚠️ backwards-incompatible if you extended Google Generator)

### Fixed
 - Spaces replaced by "+" on Outlook.com #109

## 1.4.2 - 2020-09-01
### Changed
 - Simplify extending of ICS Generator

## 1.4.1 - 2020-08-27
### Changed
 - Simplify extending of WebOutlook (e.g. for Office365)
 - Yahoo: use `allday` parameter only for a single-day events
 - Improve exception hierarchy: `InvalidLink` now extends `\InvalidArgumentException`

### Added
 - Add more tests, reorganize existing

## 1.4.0 - 2020-05-02
### Added
- Allow specifying custom `UID` ICS links (https://github.com/spatie/calendar-links/pull/85)
- Support PHP 8.0
- Support immutable dates (`\DateTimeImmutable::class`)

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
