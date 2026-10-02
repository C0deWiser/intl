# Intl service architecture

The `IntlManager` is the main entry point for this package - a Laravel service that provides a fluent API to PHP's ext-intl functions.

## IntlManager

Located at `src/IntlManager.php`. The manager constructs formatter instances, passing configuration (timezone, calendar, currency, transliterator) and the translator. It also sets loggers on formatters via `withLogger()` if available.

Constructor signature:
```php
public function __construct(
    protected Translator $translator,
    protected string $timezone,
    protected int $calendar,
    protected string $currency,
    protected string $transliterator,
)
```

Methods:
- `date(DateTimeInterface $datetime): DateFormatter` - Create date formatter
- `period(DateTimeInterface|DatePeriod|array $period, ?DateTimeInterface $end = null): PeriodFormatter` - Create period formatter (normalizes various inputs to DatePeriod)
- `number(int|float $num): NumberFormatter` - Create number formatter
- `locale(?string $locale = null): LocaleFormatter` - Create locale formatter (defaults to app locale)
- `currency(?string $currency = null): CurrencyFormatter` - Create currency formatter (defaults to configured currency)
- `text(): Transliterator` - Create transliterator

The manager uses traits to configure defaults: `ConfigureTimezone`, `ConfigureCurrency`, `ConfigureCalendar`, `ConfigureTransliterator`.

## Service Provider

`IntlServiceProvider` registers `IntlManager` as a singleton, binding it to `app/intl` container. Default configuration:
- Timezone from `config('app.timezone')`
- Calendar: `\IntlDateFormatter::GREGORIAN`
- Currency: `'EUR'` (default)
- Transliterator: `Transliterator::ANY_LATIN`

It also loads translations from `lang/` and publishes them with tag `intl`.

## Helper

`intl()` function in `src/helpers.php` returns the bound `IntlManager` instance: `app(IntlManager::class)`. It is registered via composer autoload files.

## Configuration

Users can customize defaults by extending the bound service in `AppServiceProvider`:
```php
$this->app->extend(IntlManager::class, fn (IntlManager $intl) => $intl
    ->useCalendar(\IntlDateFormatter::GREGORIAN)
    ->useCurrency('USD')
    ->useTransliterator('Any-Latin')
);
```

The configuration traits on the manager allow these fluent setters.

## Translations

Translation keys use the `intl::` namespace. Translation files are in `lang/{locale}/` for both en and ru. The package ships with translation strings for relative dates/periods - these are consumed by formatters (e.g., PeriodFormatter and DateFormatter's relative method). The test Translator reads from disk to ensure tests match actual shipped translations.