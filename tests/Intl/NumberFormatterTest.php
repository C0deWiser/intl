<?php

namespace Codewiser\Intl\Tests\Intl;

use Codewiser\Intl\Intl\NumberFormatter;
use Codewiser\Intl\IntlManager;
use Codewiser\Intl\Tests\Translator;
use IntlDateFormatter;
use Orchestra\Testbench\PHPUnit\TestCase;

class NumberFormatterTest extends TestCase
{
    /**
     * Independent reference formatting, straight from ICU.
     *
     * Asserting against this instead of hard-coded strings keeps the tests
     * stable across ICU/CLDR versions while still proving the locale, the
     * style and the currency reach the formatter.
     */
    protected function ref(int|float $num, int $format, string $locale = 'en'): ?string
    {
        return numfmt_create($locale, $format)?->format($num) ?: null;
    }

    protected function refCurrency(int|float $num, string $currency, string $locale = 'en'): ?string
    {
        return numfmt_create($locale, \NumberFormatter::CURRENCY)?->formatCurrency($num, $currency) ?: null;
    }

    protected function formatter(
        int|float $num,
        string $locale = 'en',
        string $currency = 'EUR',
    ): NumberFormatter {
        return new NumberFormatter($num, new Translator($locale), $currency);
    }

    protected function intl(string $locale = 'en', string $currency = 'EUR'): IntlManager
    {
        return new IntlManager(new Translator($locale), 'UTC', IntlDateFormatter::GREGORIAN, $currency, '');
    }

    // --------------------------------------------------------------- format

    public function test_format(): void
    {
        $this->assertSame(
            $this->ref(1234.56, \NumberFormatter::DECIMAL),
            $this->formatter(1234.56)->format(\NumberFormatter::DECIMAL)
        );
    }

    public function test_format_covers_the_usual_styles(): void
    {
        foreach ([
                     'DECIMAL'         => \NumberFormatter::DECIMAL,
                     'PERCENT'         => \NumberFormatter::PERCENT,
                     'SCIENTIFIC'      => \NumberFormatter::SCIENTIFIC,
                     'SPELLOUT'        => \NumberFormatter::SPELLOUT,
                     'ORDINAL'         => \NumberFormatter::ORDINAL,
                     'DURATION'        => \NumberFormatter::DURATION,
                     'PATTERN_DECIMAL' => \NumberFormatter::PATTERN_DECIMAL,
                 ] as $label => $style) {
            $this->assertSame(
                $this->ref(1234.56, $style),
                $this->formatter(1234.56)->format($style),
                "Number style {$label} does not match the reference formatter"
            );
        }
    }

    public function test_format_takes_integers_and_floats_alike(): void
    {
        foreach ([1234, 1234.0, 1234.56] as $num) {
            $this->assertSame(
                $this->ref($num, \NumberFormatter::DECIMAL),
                $this->formatter($num)->format(\NumberFormatter::DECIMAL),
                'An integer and its float form must format identically'
            );
        }
    }

    public function test_format_negative_and_fractional_numbers(): void
    {
        foreach ([-1234.56, -0.5, 0.5, 0.001] as $num) {
            $this->assertSame(
                $this->ref($num, \NumberFormatter::DECIMAL),
                $this->formatter($num)->format(\NumberFormatter::DECIMAL),
                "Number {$num} does not match the reference formatter"
            );
        }
    }

    public function test_format_uses_translator_locale(): void
    {
        $english = $this->formatter(1234.56, 'en')->format(\NumberFormatter::DECIMAL);
        $russian = $this->formatter(1234.56, 'ru')->format(\NumberFormatter::DECIMAL);
        $french = $this->formatter(1234.56, 'fr')->format(\NumberFormatter::DECIMAL);

        $this->assertSame($this->ref(1234.56, \NumberFormatter::DECIMAL, 'en'), $english);
        $this->assertSame($this->ref(1234.56, \NumberFormatter::DECIMAL, 'ru'), $russian);

        $this->assertNotSame($english, $russian, 'The locale must reach the formatter');
        $this->assertNotSame($english, $french, 'The locale must reach the formatter');
    }

    /**
     * Every shortcut, and the style it stands for.
     *
     * @return array<string, int>
     */
    protected function shortcuts(): array
    {
        return [
            'decimal' => \NumberFormatter::DECIMAL,
            'percent' => \NumberFormatter::PERCENT,
            'scientific' => \NumberFormatter::SCIENTIFIC,
            'spellout' => \NumberFormatter::SPELLOUT,
            'ordinal' => \NumberFormatter::ORDINAL,
            'duration' => \NumberFormatter::DURATION,
        ];
    }

    public function test_shortcuts_imply_their_style(): void
    {
        $formatter = $this->formatter(1234.56);

        foreach ($this->shortcuts() as $method => $style) {
            $this->assertSame(
                $formatter->format($style),
                $formatter->{$method}(),
                "{$method}() must imply the style it is named after"
            );
        }
    }

    public function test_shortcuts_match_the_reference_formatter(): void
    {
        foreach ($this->shortcuts() as $method => $style) {
            $this->assertSame(
                $this->ref(1234.56, $style),
                $this->formatter(1234.56)->{$method}(),
                "{$method}() does not match the reference formatter"
            );
        }
    }

    public function test_shortcuts_follow_the_translator_locale(): void
    {
        foreach (['decimal', 'percent'] as $method) {
            $english = $this->formatter(1234.56, 'en')->{$method}();
            $russian = $this->formatter(1234.56, 'ru')->{$method}();

            $this->assertSame(
                $this->ref(1234.56, $this->shortcuts()[$method], 'en'),
                $english,
                "{$method}() in en does not match the reference formatter"
            );

            $this->assertSame(
                $this->ref(1234.56, $this->shortcuts()[$method], 'ru'),
                $russian,
                "{$method}() in ru does not match the reference formatter"
            );

            $this->assertNotSame($english, $russian, "The locale must reach {$method}()");
        }
    }

    public function test_shortcuts_keep_the_zero_quirk(): void
    {
        // A shortcut must not become a second implementation: the falsy result
        // swallows a bare zero here just as it does in format(). Only DECIMAL
        // spells a zero that bare, so it is the only shortcut that nulls.
        $this->assertNull($this->formatter(0)->decimal(), 'Zero formats as "0"');

        foreach ($this->shortcuts() as $method => $style) {
            if ($method === 'decimal') {
                continue;
            }

            $this->assertSame(
                $this->ref(0, $style),
                $this->formatter(0)->{$method}(),
                "{$method}() decorates a zero, so it must not be swallowed"
            );
        }
    }

    public function test_shortcuts_take_the_number_they_are_built_with(): void
    {
        $this->assertSame(
            $this->ref(1234.56, \NumberFormatter::DECIMAL),
            $this->formatter(1234.56)->decimal(),
            'The shortcut must format its own number, not a shared one'
        );

        $this->assertSame(
            $this->ref(7, \NumberFormatter::DECIMAL),
            $this->formatter(7)->decimal()
        );
    }

    public function test_unknown_style_yields_null(): void
    {
        $this->assertNull(
            $this->formatter(1234.56)->format(9999),
            'ICU refuses to construct a formatter for an unknown style'
        );
    }

    public function test_unknown_locale_falls_back_instead_of_null(): void
    {
        // Unlike an unknown date locale, an unknown number locale still yields a
        // formatter, falling back to the root locale. No null, no Error.
        $this->assertSame(
            $this->ref(1234.56, \NumberFormatter::DECIMAL, 'xx_YY'),
            $this->formatter(1234.56, 'xx_YY')->format(\NumberFormatter::DECIMAL)
        );
    }

    public function test_zero_yields_null(): void
    {
        // An empty result is treated as no result, and "0" is an empty result as
        // far as the falsy check goes.
        $this->assertNull($this->formatter(0)->format(\NumberFormatter::DECIMAL), 'Zero formats as "0"');
        $this->assertNull($this->formatter(0.0)->format(\NumberFormatter::DECIMAL), 'And as "0" for a float');
    }

    public function test_only_the_bare_zero_is_swallowed(): void
    {
        // It is the falsy result that is dropped, not the number itself: any
        // style that decorates a zero still comes back.
        $this->assertSame('0%', $this->formatter(0)->format(\NumberFormatter::PERCENT));
        $this->assertSame(
            $this->ref(0, \NumberFormatter::PERCENT),
            $this->formatter(0)->format(\NumberFormatter::PERCENT)
        );
    }

    // ------------------------------------------------------------- currency

    public function test_currency(): void
    {
        $this->assertSame(
            $this->refCurrency(1234.56, 'USD'),
            $this->formatter(1234.56)->currency('USD')
        );
    }

    public function test_currency_falls_back_to_the_constructor_currency(): void
    {
        $formatter = $this->formatter(1234.56, 'en', 'RUB');

        $this->assertSame($this->refCurrency(1234.56, 'RUB'), $formatter->currency());
        $this->assertSame($formatter->currency(), $formatter->currency(null),
            'Passing null must fall back, not format without a currency');
    }

    public function test_currency_argument_overrides_the_constructor_currency(): void
    {
        $formatter = $this->formatter(1234.56, 'en', 'EUR');

        $this->assertSame($this->refCurrency(1234.56, 'USD'), $formatter->currency('USD'));
        $this->assertNotSame($formatter->currency(), $formatter->currency('USD'),
            'An explicit currency must win over the constructor one');
    }

    public function test_format_currency_delegates_to_currency(): void
    {
        $formatter = $this->formatter(1234.56, 'en', 'USD');

        $this->assertSame(
            $formatter->currency(),
            $formatter->format(\NumberFormatter::CURRENCY),
            'The CURRENCY style is a shortcut to currency()'
        );
    }

    public function test_currency_uses_translator_locale(): void
    {
        $english = $this->formatter(1234.56, 'en')->currency('USD');
        $russian = $this->formatter(1234.56, 'ru')->currency('USD');
        $french = $this->formatter(1234.56, 'fr')->currency('USD');

        $this->assertSame($this->refCurrency(1234.56, 'USD', 'en'), $english);
        $this->assertSame($this->refCurrency(1234.56, 'USD', 'ru'), $russian);

        $this->assertNotSame($english, $russian, 'The locale must reach the formatter');
        $this->assertNotSame($english, $french, 'The locale must reach the formatter');
    }

    public function test_currency_of_zero_keeps_the_decimals(): void
    {
        $this->assertSame(
            $this->refCurrency(0, 'USD'),
            $this->formatter(0)->currency('USD'),
            'A zero currency value is not the bare "0", so it is kept'
        );
    }

    public function test_currency_ignores_unknown_codes(): void
    {
        // ICU does not validate the code: it falls back to the generic ¤ symbol,
        // so this degrades instead of yielding null.
        $formatter = $this->formatter(1234.56);

        $this->assertSame($this->refCurrency(1234.56, 'XXXX'), $formatter->currency('XXXX'));
        $this->assertNotNull($formatter->currency('XXXX'), 'An unknown code must not null the value');
    }

    public function test_currency_is_case_insensitive(): void
    {
        $formatter = $this->formatter(1234.56);

        $this->assertSame($formatter->currency('USD'), $formatter->currency('usd'));
    }

    // -------------------------------------------------------------- manager

    public function test_manager_number_returns_a_number_formatter(): void
    {
        $this->assertInstanceOf(NumberFormatter::class, $this->intl()->number(1234.56));
    }

    public function test_manager_number_uses_its_locale_and_currency(): void
    {
        $formatter = $this->intl('ru', 'RUB')->number(1234.56);

        $this->assertSame(
            $this->ref(1234.56, \NumberFormatter::DECIMAL, 'ru'),
            $formatter->format(\NumberFormatter::DECIMAL),
            'The manager locale must reach the formatter'
        );

        $this->assertSame($this->refCurrency(1234.56, 'RUB', 'ru'), $formatter->currency(),
            'The manager currency must reach the formatter');
    }

    public function test_manager_use_currency_is_fluent(): void
    {
        $intl = $this->intl();

        $this->assertSame($intl, $intl->useCurrency('JPY'));
    }

    public function test_manager_use_currency_affects_later_calls(): void
    {
        $intl = $this->intl('en', 'EUR');

        $before = $intl->number(1234.56)->currency();
        $after = $intl->useCurrency('JPY')->number(1234.56)->currency();

        $this->assertSame($this->refCurrency(1234.56, 'EUR'), $before);
        $this->assertSame($this->refCurrency(1234.56, 'JPY'), $after);
        $this->assertNotSame($before, $after, 'The currency must reach every later call');
    }

    // ----------------------------------------------------------- macroable

    public function test_number_formatter_is_macroable(): void
    {
        $formatter = $this->formatter(1234.56);

        NumberFormatter::macro('shout', fn() => 'SHOUT');

        try {
            $this->assertTrue(NumberFormatter::hasMacro('shout'));
            $this->assertSame('SHOUT', $formatter->shout());
        } finally {
            NumberFormatter::flushMacros();
        }

        $this->assertFalse(NumberFormatter::hasMacro('shout'));
    }
}