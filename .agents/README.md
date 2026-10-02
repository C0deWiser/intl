# Codewiser\Intl

Laravel casts for localized attributes. An attribute holds a map of locales to
values, and the cast hands out a plain value in the current locale unless
hydration is asked for.

## Commands

    vendor/bin/phpunit --no-coverage          # whole suite
    vendor/bin/phpunit --filter Multilingual # one group
    php -l src/Casts/Hydrate.php             # lint

## Layout

    src/Casts/     Multilingual, Hydrate, AsMultilingual, AsCollection,
                   CastsMultilingual
    src/Intl/      DateFormatter, PeriodFormatter, NumberFormatter,
                   LocaleFormatter, CurrencyFormatter, Transliterator,
                   Traits/Configure{Timezone,Calendar,Currency,Transliterator}
    src/Traits/    HasMultilingual (model side)
    lang/          intl::* translation files, en and ru only
    tests/         Intl/ (one test file per formatter; SkeletonTest and
                   RelativeTest carry the DateFormatter methods that need
                   their own fixtures, and the period tests live in
                   DateFormatterTest), Casts/, Translator.php

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

- [hydration.md](hydration.md) — live objects, invalidation, write-back
- [casts.md](casts.md) — Eloquent cast internals that shaped this package
- [icu.md](icu.md) — how ext-intl fails, and which failures are deliberate