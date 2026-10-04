# Intl Helper

`IntlManager` service is a helper to access main `intl` PHP functions.

## Configuration

As `IntlManager` is a proxy to any `intl` functions, it requires some
attributes to be pre-configured:

* `Timezone` and `Calendar` — for [IntlDateFormatter](https://www.php.net/manual/en/intldateformatter.create.php)
* `Currency` — for [NumberFormatter](https://www.php.net/manual/en/numberformatter.formatcurrency.php)
* `Transliterator ID` — for [Transliterator](https://www.php.net/manual/en/transliterator.create.php)
* `Locale` — for most of them.

Out-of-the-box `IntlManager` uses config values, so you may
configure `IntlManager` in your app's `config/app.php` file:

```php
config('app.timezone', 'UTC'),
config('app.currency', 'EUR'),
config('app.calendar', \IntlDateFormatter::GREGORIAN),
config('app.transliterator', Transliterator::ANY_LATIN)
```

Another way you may configure `IntlManager` in your application's
`AppServiceProvider` class:

```php
use Codewiser\Intl\IntlManager;
use Codewiser\Intl\Intl\Transliterator;

/**
 * Register any application services.
 */
public function register(): void
{
    $this->app->extend(IntlManager::class, fn (IntlManager $intl) => $intl
        ->useCurrency('EUR')
        ->useCalendar(\IntlDateFormatter::GREGORIAN)
        ->useTransliterator(Transliterator::ANY_LATIN)
    );
}
```

`intl()` is a synonym of `app(IntlManager::class)`.

## DateTime

Format date and time respecting the app's current locale.

```php
intl()->date($date)->format(
    date: \IntlDateFormatter::FULL,
    time: \IntlDateFormatter::SHORT
);
// Saturday, April 12, 1952 at 3:30 PM

intl()->date($date)->format(date: \IntlDateFormatter::SHORT);
// 4/12/52

intl()->date($date)->format(time: \IntlDateFormatter::FULL);
// 3:30:42 PM Coordinated Universal Time
```

Date formatter has a few shorthand functions:

```php
intl()->date($date)->short();
// 4/12/52 3:30 PM

intl()->date($date)->full();
// Saturday, April 12, 1952 at 3:30:42 PM Coordinated Universal Time

// etc.
```

### Skeletons

The styles above only offer the fixed `LONG`/`MEDIUM`/`SHORT` sets, so they
cannot express "Sat, Oct 3" or "Q4 2026". A skeleton names the fields to
show and lets ICU
[choose the pattern](https://www.php.net/manual/en/intldatepatterngenerator.getbestpattern.php)
that the locale prefers:

```php
intl()->date($date)->skeleton('yMMMEd');
// Sat, Oct 3, 2026

intl()->date($date)->skeleton('yQQQ');
// Q4 2026

intl()->date($date)->skeleton('Hm');
// 15:04
```

## DatePeriod

Format date period respecting the app's current locale.

Date period may be passed either as a `\DatePeriod` object,
or as two `\DateTimeInterface` objects (array or variadic).

```php
$period = now()->toPeriod(now()->addHour());

intl()
    ->period($period)
    ->format(\IntlDateFormatter::LONG, \IntlDateFormatter::LONG);
// April 12, 1952 from 3:30:42 PM UTC to 4:30:42 PM UTC
```

### Translations

The period sentences come from the translations shipped with the package.
Publish them to override:

```shell
php artisan vendor:publish --tag=intl
```

## Numbers

```php
// Shortcuts:

// Use default app currency
intl()->number(1234.56)->currency();      // €1,234.56

// Override default currency
intl()->number(1234.56)->currency('USD'); // $1,234.56

intl()->number(1234.56)->decimal();       // 1,234.56
intl()->number(0.564)->percent();         // 56.4%
intl()->number(1234.56)->spellout();      // one thousand two hundred thirty-four point five six
intl()->number(1234.56)->ordinal();       // 1,235th
intl()->number(1234.56)->duration();      // 20:35
intl()->number(1234.56)->scientific();    // 1.23456E3

// Base:
intl()->number(1234.56)->format(\NumberFormatter::DECIMAL);
```

## Names

A locale and a currency both have names, which is what a language switcher and a
currency selector need:

```php
// The app's default locale
intl()->locale()->display();
// English

intl()->locale('zh_Hant_TW')->display();
// Chinese (Traditional, Taiwan)

intl()->locale('pt_BR')->region();
// Brazil

intl()->locale('de')->display('ru');
// немецкий

// The app's default currency
intl()->currency()->name();
// Euro

intl()->currency('RUB')->name('fr');
// rouble russe

intl()->currency('RUB')->symbol('ru');
// ₽ in a Russian locale

intl()->currency('RUB')->symbol('en');
// RUB in an English one
```

## Transliteration

Useful for turning a name into something a URL or a search box can carry:

```php
use Codewiser\Intl\Intl\Transliterator;

intl()->text()->convert('Шёлковый пух', Transliterator::RUSSIAN_LATIN);
// Shëlkovyy pukh

intl()->text()->convert('北京', Transliterator::HAN_LATIN);
// běi jīng
```

Normalization is the same machinery and matters because Unicode writes the same
text in more than one way:

```php
intl()->text()->normalize("e\u{0301}");     // é as one code point
intl()->text()->isNormalized($text);        // already in NFC?
```