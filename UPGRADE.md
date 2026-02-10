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