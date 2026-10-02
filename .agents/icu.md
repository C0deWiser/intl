# ICU behaviour

What `ext-intl` actually does on failure, and which of it is deliberate.
Verified on PHP 8.2.30, ICU 78.1. The tests assert against direct ICU calls
instead of hard-coded strings, so they survive an ICU upgrade — but the failure
modes below are structural, not version-specific.

## Dates

`DateFormatter::formatDateTime()` calls `datefmt_create()` and then formats.
Three ways that can go wrong, and they do **not** fail alike:

| Input | `datefmt_create()` returns | Result |
| --- | --- | --- |
| Bad timezone | `null` | `null` — the `?->` covers it |
| Bad calendar | `null` | `null` — the `?->` covers it |
| Unknown locale | an **unconstructed** `IntlDateFormatter` | `Error` |

The last one is the trap. `?->` short-circuits on `null`, not on a broken
object, and calling `format()` on it throws
`Error: Found unconstructed IntlDateFormatter`. `intl_get_error_message()`
reports `U_ZERO_ERROR` while it is happening, so it cannot be used to detect
this either.

**Raising the Error is deliberate.** A locale nobody can format for is a bug to
hear about; a bad timezone or calendar is plausibly a display preference and
degrades to `null`. The `Error` is therefore not caught anywhere, and
`format(): ?string` documents the two degrading cases only.

Do not "fix" this by re-adding a `catch (Error)` — an earlier version had one,
and it was removed on purpose. `test_unknown_locale_throws` and
`test_period_with_unknown_locale_throws` pin the behaviour.

## Numbers

`NumberFormatter` reads the locale off the translator, the same way, but
`numfmt_create()` misbehaves differently:

| Input | Result |
| --- | --- |
| Unknown style, e.g. `9999` | `null` |
| Unknown currency code, e.g. `XXXX` | a value with the generic `¤` symbol, **not** `null` |
| Unknown locale, e.g. `xx_YY` | a valid formatter, falling back to the root locale |

So the two classes disagree on purpose: an unknown *style* is a programmer
error and yields `null`, while an unknown *locale* quietly falls back — the
opposite of what dates do. Do not unify them without a decision.

## The bare zero

`NumberFormatter::format()` and `currency()` end in `?: null`, so any falsy
result becomes `null`. `format(DECIMAL)` of `0` gives `'0'`, which is falsy, so
a zero **is** swallowed. It is the result that is dropped, not the number:
`format(PERCENT)` of the same zero gives `'0%'` and survives, and
`currency('USD')` of `0` gives `'$0.00'`.

If a zero ever has to format as `'0'`, that check needs narrowing to `=== ''`.

## Passing null to formatCurrency

`formatCurrency($num, null)` is deprecated since PHP 8.2. `currency()` gets away
with passing `null` in its signature because it resolves the argument first:
`$currency ?? $this->currency`, with the constructor's `$currency` typed
`string`. Keep that resolution order.

## Locale notes

`formatCurrency` does not validate codes: `NOPE` formats as `NOP 1,234.56` and
lowercase `usd` works. Currency placement is per locale, not per code —
`1,234.56` USD comes out as `$1,234.56` in `en`, `1 234,56 $` in `ru`,
`1 234,56 $US` in `fr`, so the currency assertions must go through the
reference formatter too.

The currency **bundle** behaves unlike `formatCurrency` in one way that matters:
it is keyed uppercase and does not fold, so `usd` is simply absent where
`formatCurrency` would have accepted it. `CurrencyFormatter` uppercases the code
before the lookup. The narrow symbol, index 2 of an entry, is absent in ICU 78.

## Naming a locale

`locale_get_display_*()` never admits that it cannot name something — it echoes
the subtag back. `locale_get_display_language('xx_YY')` is `'xx'` and
`locale_get_display_name('zz_US')` is `'zz (United States)'`. A name equal to the
subtag it would be an echo of is therefore not a name, which is how
`LocaleFormatter` tells the two apart.

The echo has to be checked per part, and the subtag readers are **not** named
symmetrically with the display ones:

| Display | Subtag |
| --- | --- |
| `locale_get_display_language` | `locale_get_primary_language` |
| `locale_get_display_script` | `locale_get_script` |
| `locale_get_display_region` | `locale_get_region` |
| `locale_get_display_name` | none |

There is no `locale_get_primary_script` or `locale_get_primary_region`; guessing
the name and passing the string to a `callable` parameter fails at runtime with
a plain `TypeError`. `display()` cannot use the identity check at all — the echo
ends up inside brackets — so it checks `language()` instead and stops there.

`und` and `root` carry no language: the primary subtag is empty and ICU answers
"Unknown language". Empty is the signal, not the phrase.

## Skeletons

`IntlDatePatternGenerator::getBestPattern()` does **not** validate its input:

| Skeleton | Best pattern |
| --- | --- |
| `yMMMd` | `MMM d, y` |
| `''` or `'!!!'` | `''` — no field to map |
| `ZZZZZZZZ` | `'ZZZZZZZZ'` — passed through verbatim |

So an empty result is the only reliable "no fields" signal, and garbage in
becomes garbage out. Do not try to detect a bad skeleton by comparing the
pattern to the input: `GGGG`, `d` and `y` all legitimately map to themselves.

A generator on an **unknown locale** returns the *default locale's* pattern
rather than raising. The `IntlDateFormatter` built from it then raises the same
`Error` as `format()` does, which is why `skeleton()` needs no locale handling
of its own.

## ICU messages

`msgfmt_create()` answers **`null`** for a pattern it cannot parse, so `?->` is
enough. One rule is easy to trip over: a `select` **must** carry an `other`
branch, or the whole message is rejected. `{1, select, past { ago} future { now}}`
is invalid ICU; adding `other { now}` makes it valid.

Inside a `plural`, `#` is number-formatted for the locale, thousands and all —
`ru` gives `1 234 дня`, `de` gives `1.000 Tage`. That is why `relative()` needs
no separate `{0, number}` argument. Passing a *string* instead of an int skips
the number formatting.

`MessageFormatter::formatMessage()` is the one-shot form, but it returns `false`
rather than `null` on a bad pattern, so the `msgfmt_create()` + `?->` form is
used throughout for a single failure mode.

## ResourceBundle

PHP **8.5** added `IntlListFormatter`, which covers list formatting natively, so
the package ships no list formatter of its own. On 8.2–8.4 there is no
replacement, which is a feature gap rather than something to reimplement.

`ResourceBundle($locale, 'ICUDATA')` is still the way to reach CLDR data the
classes do not expose — currency names, for one. It is **not** an array:
`$bundle['Currencies']` is a fatal `TypeError`; it is `$bundle->get('Currencies')`,
which answers `null` for anything it lacks.

## Normalization

`NFC` and `NFD` work through `Transliterator::create()` but are **absent from
`listIDs()`**, so a rule listing never mentions them. That is not an oversight,
and `listRules()` is not the place to document them.

## Locale-aware case

Not available. `mb_strtoupper('i')` and `IntlChar::toupper()` both return `I`
regardless of locale, so `Str::upper()` can never be correct for Turkish. Worth
knowing before reaching for it.