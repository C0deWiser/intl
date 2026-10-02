<?php

namespace Codewiser\Intl\Tests\Intl;

use Codewiser\Intl\Intl\CurrencyFormatter;
use Codewiser\Intl\IntlManager;
use Codewiser\Intl\Tests\Translator;
use Orchestra\Testbench\PHPUnit\TestCase;
use ResourceBundle;

class CurrencyFormatterTest extends TestCase
{
    /**
     * Independent reference, straight from the ICU currency bundle.
     *
     * The index constants are the ones the ICU data layout uses: the symbol
     * comes before the name in every entry.
     */
    protected function refName(string $currency, string $locale): ?string
    {
        return (new ResourceBundle($locale, 'ICUDATA-curr'))
            ->get('Currencies')
            ?->get(strtoupper($currency))
            ?->get(1);
    }

    protected function refSymbol(string $currency, string $locale): ?string
    {
        return (new ResourceBundle($locale, 'ICUDATA-curr'))
            ->get('Currencies')
            ?->get(strtoupper($currency))
            ?->get(0);
    }

    /**
     * @param  string  $inLocale  The language the app is in.
     * @param  string|null  $currency  The currency to name.
     */
    protected function currency(string $inLocale = 'en', ?string $currency = 'EUR'): CurrencyFormatter
    {
        return new CurrencyFormatter(new Translator($inLocale), $currency);
    }

    // ----------------------------------------------------------------- names

    public function test_it_names_a_currency(): void
    {
        foreach (['en', 'ru', 'fr', 'de'] as $locale) {
            foreach (['USD', 'EUR', 'RUB', 'JPY'] as $currency) {
                $this->assertSame(
                    $this->refName($currency, $locale),
                    $this->currency($locale, $currency)->name(),
                    "The {$currency} name in {$locale} does not match the reference"
                );
            }
        }
    }

    public function test_it_gives_a_symbol(): void
    {
        foreach (['en', 'ru'] as $locale) {
            $this->assertSame(
                $this->refSymbol('RUB', $locale),
                $this->currency($locale, 'RUB')->symbol(),
                "The RUB symbol in {$locale} does not match the reference"
            );
        }
    }

    public function test_the_name_is_written_in_the_requested_language(): void
    {
        $formatter1 = $this->currency('en', 'RUB');
        $formatter2 = $this->currency('ru', 'RUB');

        $this->assertSame('Russian Ruble', $formatter1->name());
        $this->assertSame('российский рубль', $formatter2->name());
    }

    public function test_it_defaults_to_the_translator_locale(): void
    {
        $formatter = new CurrencyFormatter(new Translator('ru'), 'EUR');

        $this->assertSame($this->refName('EUR', 'ru'), $formatter->name());
    }

    // ----------------------------------------------------------------- codes

    public function test_it_folds_the_code_case(): void
    {
        // The bundle is keyed uppercase and does not fold, so "usd" would be
        // simply absent without this.
        $this->assertSame($this->currency('en', 'USD')->name(), $this->currency('en', 'usd')->name());
        $this->assertSame($this->currency('en', 'USD')->name(), $this->currency('en', 'Usd')->name());
        $this->assertSame($this->currency('en', 'USD')->symbol(), $this->currency('en', 'usd')->symbol());
    }

    public function test_an_unknown_code_yields_null(): void
    {
        $this->assertNull(
            $this->currency('en', 'XXXX')->name(),
            'A code ICU does not know has no name'
        );

        $this->assertNull(
            $this->currency('en', 'XXXX')->symbol(),
            'A code ICU does not know has no symbol'
        );
    }

    public function test_a_missing_code_yields_null(): void
    {
        $this->assertNull(
            (new CurrencyFormatter(new Translator('en'), ''))->name(),
            'With neither a given code nor an app currency there is nothing to name'
        );

        $this->assertNull(
            (new CurrencyFormatter(new Translator('en'), ''))->name(),
            'An empty code is not a code'
        );
    }

    // ------------------------------------------------------- against formats

    public function test_it_agrees_with_the_formatted_amount(): void
    {
        // A currency selector should not offer a name that contradicts the
        // symbol the formatter prints.
        $intl = new IntlManager(new Translator('ru'), 'UTC', \IntlDateFormatter::GREGORIAN, 'RUB', '');

        $this->assertSame('₽', $intl->currency('RUB')->symbol());
        $this->assertSame('российский рубль', $intl->currency('RUB')->name());

        $this->assertStringContainsString(
            '₽',
            (string) $intl->number(1234.56)->currency('RUB'),
            'The named currency must be the formatted one'
        );
    }

    // ------------------------------------------------------------ the facade

    public function test_the_manager_uses_the_configured_currency(): void
    {
        $intl = new IntlManager(new Translator('en'), 'UTC', \IntlDateFormatter::GREGORIAN, 'JPY', '');

        $this->assertSame(
            $this->refName('JPY', 'en'),
            $intl->currency('JPY')->name(),
            'Without an explicit code the manager currency is named'
        );

        $this->assertSame(
            $this->refName('USD', 'en'),
            $intl->currency('USD')->name(),
            'An explicit code wins over the configured one'
        );
    }

    public function test_it_is_macroable(): void
    {
        CurrencyFormatter::macro('shout', fn() => 'SHOUT');

        try {
            $this->assertSame('SHOUT', $this->currency()->shout());
        } finally {
            CurrencyFormatter::flushMacros();
        }

        $this->assertFalse(CurrencyFormatter::hasMacro('shout'));
    }
}