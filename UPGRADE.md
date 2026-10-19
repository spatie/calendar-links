# Upgrading from 2.x to 3.0

These changes are for the next major release only, not a 2.x patch or minor release.

## Timed ICS events retain their timezone

Timed events with resolvable timezones now write local `DTSTART` and `DTEND` values with `TZID` parameters and one `VTIMEZONE` component per referenced zone. This includes non-recurring events in a single zone, `UTC`, and regions without daylight saving such as `Asia/Tokyo`.

Previously, named zones were used only for events with distinct resolvable endpoint zones, or recurring events in a zone with clock changes. Other timed events used UTC endpoints with a `Z` suffix and no timezone component.

For a single-zone Tokyo event starting at 09:00:

```text
Before: DTSTART:20260323T000000Z
After:  DTSTART;TZID=Asia/Tokyo:20260323T090000
```

Update consumers that assume `DTSTART:`/`DTEND:` have no parameters, require a `Z` suffix, or assume the first `DTSTART` in the file belongs to `VEVENT`: `VTIMEZONE` observances also contain `DTSTART`. Parse the event component with an iCalendar parser. Raw files and base64 data URIs change and become larger; update snapshots and invalidate cached output as needed.

Review recurrence rules with `BYHOUR`, `BYMINUTE`, or `BYDAY`: those fields now follow the named start zone instead of UTC for events whose representation changed. For example, a Tokyo rule previously using `BYHOUR=0` for 09:00 local should use `BYHOUR=9`. `UNTIL` must still be a UTC date-time for timed events.

## Repeated local times preserve the supplied instants

When clocks move backward, RFC 5545 interprets a repeated local time with `TZID` as its first occurrence. If either endpoint is the second occurrence, both endpoints now use UTC and no timezone component, preserving the supplied instants and duration. This also corrects previously named recurring and distinct-zone events. A recurring event on this fallback path repeats in UTC; review whether its future local times match your intent.

## What does not change

- PHP still requires 8.3 or later; public method signatures are unchanged.
- All-day events stay date-only, with the same inclusive end-date handling.
- Unresolvable zones still fall back to UTC; `DTSTAMP` and absolute reminder triggers remain UTC.
- Google, Yahoo, and Outlook output is unchanged.
- The protected ICS extension hooks remain available. An override of `shouldNameTimezones()` that unconditionally returns `true` bypasses the repeated-time safeguard.

Development now uses Pest 4 on PHPUnit 12, including the existing PHPUnit tests. Run `composer test`; use `composer test:update-snapshots` only when intentionally changing snapshots.

# Upgrading from 1.x to 2.0

## PHP Version

The minimum PHP version has been bumped from 8.1 to **8.3**.

## `Link::create()` no longer accepts `$allDay` flag

The `$allDay` parameter has been removed from `Link::create()`. Use `Link::createAllDay()` instead.

```php
// Before
$link = Link::create('Event', $from, $to, true);

// After
$link = Link::createAllDay('Event', $from, $numberOfDays);
```

## All-day event end dates are now inclusive

`Link::createAllDay()` now treats `$numberOfDays` as inclusive. Previously, a single-day event required passing the next day as the end date — the library now handles this internally.

```php
// Before: 1 day event required the +1 day adjustment to be done manually in some cases
Link::create('Event', $from, $from->modify('+1 day'), true);

// After: just pass the number of days
Link::createAllDay('Event', $from, 1);
```

If you were constructing `Link` directly, the constructor now automatically adds +1 day to the end date for all-day events. Verify your all-day events still have the correct duration.

## `$from` and `$to` are now `DateTimeImmutable`

The `Link::$from` and `Link::$to` properties are now `DateTimeImmutable` instances instead of `DateTime`. If your code calls mutable methods like `setTimezone()` on these objects, you need to capture the return value:

```php
// Before
$link->from->setTimezone(new DateTimeZone('UTC'));

// After
$utcFrom = $link->from->setTimezone(new DateTimeZone('UTC'));
```

## Properties are now public

The magic `__get()` method has been removed. All properties are now accessed as native public properties. If you were reading `$link->title`, `$link->from`, etc., no code change is needed — they continue to work.

However, `$title`, `$from`, `$to`, and `$allDay` are `public readonly` and cannot be reassigned. `$description` and `$address` are `public` and can still be set directly, though using the fluent methods is recommended.

## `InvalidLink::invalidDateRange()` has been removed

The deprecated `invalidDateRange()` method has been removed. Use `negativeDateRange()` instead:

```php
// Before
InvalidLink::invalidDateRange($to, $from);

// After
InvalidLink::negativeDateRange($from, $to);
```

Note the parameter order has also changed: `$from` comes first.

## `Link` constructor is `final`

The `Link` constructor is now `final`. If you were extending `Link` and overriding the constructor, use a static factory method instead:

```php
class CustomLink extends Link
{
    public static function createCustom(/* ... */): static
    {
        $link = parent::create(/* ... */);
        // customize...
        return $link;
    }
}
```

## `WebOffice` and `WebOutlook` are `final`

These classes can no longer be extended. If you need a custom Outlook-based generator, extend `BaseOutlook` directly.

## `BaseOutlook::baseUrl()` is now `protected`

If you were calling `baseUrl()` from outside the class, this is no longer possible. This method is an internal implementation detail of the Outlook generators.

## Generator internals are no longer overridable

Date format properties (`$dateFormat`, `$dateTimeFormat`) in `Google`, `Yahoo`, and `BaseOutlook` have been replaced with `private const` values. If you were extending these generators to override format strings, you will need to implement the `Generator` interface directly instead.
