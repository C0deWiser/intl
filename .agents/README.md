# Codewiser\Intl

Intl helper for Laravel. Provides a fluent API to format dates, periods, numbers, currencies, locales, and perform transliteration using PHP's ext-intl.

## Commands

    vendor/bin/phpunit --no-coverage
    vendor/bin/phpunit --filter <ClassName>
    vendor/bin/phpunit --filter test_method_name

## Layout

    src/
      Intl/      DateFormatter, PeriodFormatter, NumberFormatter,
                 LocaleFormatter, CurrencyFormatter, Transliterator,
                 Traits/Configure{Timezone,Calendar,Currency,Transliterator}
      IntlManager.php  - Main service class
      IntlServiceProvider.php
      helpers.php      - intl() helper function
    lang/        intl::* translation files (en, ru)
    tests/Intl/  One test file per formatter (DateFormatterTest, NumberFormatterTest,
                 CurrencyFormatterTest, LocaleFormatterTest, PeriodFormatterTest,
                 TransliteratorTest, IntlManagerTest, plus SkeletonTest and
                 RelativeTest for DateFormatter specific behaviors)
    tests/Translator.php - Test double for translations

Note the doubled namespace `Codewiser\Intl\Intl\*`: the PSR-4 root is
`Codewiser\Intl\` mapped to `src/`, so `src/Intl/X.php` is
`Codewiser\Intl\Intl\X`. It was left alone deliberately — flattening it would
touch every import for a cosmetic gain.

## Conventions

- Comments in `src/` are short: what a thing is and how to use it. Reasoning,
  dead ends and framework internals belong in these notes, not in the code.
- Tests may be verbose, and assertion messages spell out what is being checked.
- PHP 8.2+, `laravel/framework` >= 12, `ext-intl` required.

## Notes

- [intl.md](intl.md) — service architecture, manager, helpers, and configuration
- [icu.md](icu.md) — how ext-intl fails, and which failures are deliberate
- [formatters.md](formatters.md) — date/period/number/locale/currency/transliterator details