# Formatters

All formatters are in `src/Intl/` and implement fluent APIs. They use `Macroable` and `LoggerAwareTrait`, and appropriate configuration traits.

## DateFormatter

Formats individual dates using ICU. Takes a `DateTimeInterface`, translator, timezone, calendar.

### Methods

- `format(int $date = IntlDateFormatter::NONE, int $time = IntlDateFormatter::NONE): ?string` - Uses `datefmt_create()` with the app's locale, timezone, calendar. Returns null if formatter creation fails or formatting returns falsy. Known issue: unknown locale causes an unconstructed IntlDateFormatter that throws Error (deliberate - see icu.md).
- `skeleton(string $skeleton): ?string` - Uses `IntlDatePatternGenerator` to get best pattern for CLDR skeleton (e.g., 'yMMMd', 'yQQQ', 'Hm'). Returns null if pattern is empty (no fields to map). Unknown locale behavior is deferred to IntlDateFormatter throwing Error.
- `relative(?DateTimeInterface $relativeTo = null): ?string` - Calculates difference and formats using ICU MessageFormatter with translation strings from `intl::relative`. Uses coarsest non-zero unit (year/month/day/hour/minute/second). Defaults to "now" in configured timezone.

## PeriodFormatter

Formats date ranges/periods. Constructor takes a `DatePeriod`, translator, timezone, calendar.

### Methods

- `format(int $date = IntlDateFormatter::NONE, int $time = IntlDateFormatter::NONE): ?string` - Formats start and end of period. Uses `IntlDateFormatter` for each endpoint, then interpolates into translation strings from `intl::period` (e.g., "from :start to :end"). Handles cases where period has no end (returns just start with appropriate phrasing based on translations).
- Also supports skeleton-like formatting patterns as defined by translations? (see actual implementation in PeriodFormatter)

PeriodFormatter works with DatePeriod objects. IntlManager normalizes inputs - accepts DatePeriod directly, or array of two DateTimeInterface, or start DateTimeInterface with end parameter.

## NumberFormatter

Formats numbers with various styles.

### Methods

- `format(int $style = NumberFormatter::DECIMAL): ?string` - Base formatter using PHP's NumberFormatter. Returns null if result is falsy (including '0' for decimal format in some cases - see icu.md).
- Convenience methods:
  - `decimal(): ?string`
  - `percent(): ?string` 
  - `currency(string $currency = null): ?string` - Resolves currency (uses provided or configured default). Note: passes resolved currency to formatCurrency.
  - `spellout(): ?string`
  - `ordinal(): ?string`
  - `duration(): ?string`
  - `scientific(): ?string`

Uses translator's locale. Currency resolution prefers explicit param over configured currency.

## LocaleFormatter

Names locales for display purposes (useful for language switchers).

### Methods

- `display(string $locale = null): ?string` - Display name of locale in current locale (or specified locale context). Handles edge cases like echoing back subtags (see icu.md).
- `language(string $locale = null): ?string` - Display language name
- `script(string $locale = null): ?string` - Display script name  
- `region(string $locale = null): ?string` - Display region name

## CurrencyFormatter

Names currencies.

### Methods

- `name(string $locale = null): ?string` - Get localized currency name
- `symbol(string $locale = null): ?string` - Get currency symbol for locale
- `code(): string` - Get currency code

Uppercases currency codes before lookup (important as ICU data is keyed uppercase).

## Transliterator

Handles text conversion/transliteration and Unicode normalization.

### Methods

- `convert(string $string, string $transliterator = null): ?string` - Convert text using transliterator. Defaults to configured transliterator.
- `normalize(string $string, int $form = Transliterator::FORM_C): ?string` - Normalize Unicode (NFC/NFD forms)
- `isNormalized(string $string, int $form = Transliterator::FORM_C): bool` - Check if normalized

Constants for forms: `FORM_C`, `FORM_D`, `FORM_KC`, `FORM_KD`. Note: NFC/NFD aren't listed in listIDs() even though they work.