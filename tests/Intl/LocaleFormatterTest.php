<?php

namespace Codewiser\Intl\Tests\Intl;

use Codewiser\Intl\Intl\LocaleFormatter;
use Codewiser\Intl\IntlManager;
use Codewiser\Intl\Tests\Translator;
use Orchestra\Testbench\PHPUnit\TestCase;

class LocaleFormatterTest extends TestCase
{
    protected function intl(string $locale = 'en'): IntlManager
    {
        return new IntlManager(new Translator($locale), 'UTC', \IntlDateFormatter::GREGORIAN, 'EUR', '');
    }

    protected function locale(string $locale, string $inLocale = 'en'): LocaleFormatter
    {
        return new LocaleFormatter(new Translator($inLocale), $locale);
    }

    // ----------------------------------------------------------------- names

    public function test_it_names_the_parts_of_a_locale(): void
    {
        $formatter = $this->locale('zh_Hant_TW');

        // Asserted against the locale_* functions rather than fixed strings,
        // so the test survives CLDR renaming a territory.
        $this->assertSame(locale_get_display_language('zh_Hant_TW', 'en'), $formatter->language());
        $this->assertSame(locale_get_display_script('zh_Hant_TW', 'en'), $formatter->script());
        $this->assertSame(locale_get_display_region('zh_Hant_TW', 'en'), $formatter->region());
        $this->assertSame(locale_get_display_name('zh_Hant_TW', 'en'), $formatter->display());
    }

    public function test_it_names_a_whole_locale(): void
    {
        foreach (['ru_RU', 'pt_BR', 'en_US', 'zh_Hant_TW'] as $locale) {
            $name = $this->locale($locale)->display();

            $this->assertNotNull($name, "{$locale} must be nameable");

            $this->assertMatchesRegularExpression(
                '/\(.+\)/u',
                $name,
                "The full name of {$locale} carries its region"
            );
        }
    }

    public function test_a_locale_without_a_region_is_still_nameable(): void
    {
        $this->assertSame(
            locale_get_display_name('ru', 'en'),
            $this->locale('ru')->display(),
            'A bare language has no region to append'
        );
    }

    // ------------------------------------------------------------------ echo

    public function test_an_unknown_locale_yields_null(): void
    {
        // ICU echoes an unknown subtag back verbatim, which would put "xx (YY)"
        // in front of a person as the name of a language.
        $formatter = $this->locale('xx_YY');

        $this->assertSame('xx', locale_get_display_language('xx_YY', 'en'), 'ICU echoes the unknown subtag');

        $this->assertNull($formatter->display(), 'A locale ICU cannot name must not be named');
    }

    public function test_an_unknown_language_with_a_known_region(): void
    {
        // "zz_US" is an unknown language in a real country. The country is still
        // nameable, and discarding it along with the language would throw away
        // an answer ICU did give.
        $formatter = $this->locale('zz_US');

        $this->assertNull($formatter->language(), 'The language is unknown');
        $this->assertNull($formatter->display(), 'So the whole locale is unnamed');

        $this->assertSame(
            locale_get_display_region('zz_US', 'en'),
            $formatter->region(),
            'The region is a real one and stays nameable'
        );

        $this->assertSame('United States', $formatter->region());
    }

    public function test_a_locale_without_a_language_is_not_named(): void
    {
        // "und" and "root" are tags rather than languages, and ICU calls both
        // "Unknown language" — a phrase about ICU, not a name for anything.
        foreach (['und', 'root'] as $locale) {
            $formatter = $this->locale($locale);

            $this->assertSame('Unknown language', locale_get_display_language($locale, 'en'));

            $this->assertNull($formatter->language(), "{$locale} names no language");
            $this->assertNull($formatter->display(), "{$locale} has no whole name");
        }
    }

    // ----------------------------------------------------------- the language

    public function test_it_names_in_the_requested_language(): void
    {
        // A Russian reader should see "немецкий", not "German".
        $this->assertSame(
            locale_get_display_name('de', 'ru'),
            $this->locale('de')->display('ru'),
            'The display language must reach ICU'
        );

        $this->assertNotSame(
            $this->locale('de')->display('en'),
            $this->locale('de')->display('ru'),
            'Two display languages must not give the same name'
        );
    }

    public function test_it_defaults_to_the_translator_locale(): void
    {
        $formatter = new LocaleFormatter(new Translator('ru'), 'de');

        $this->assertSame(
            locale_get_display_name('de', 'ru'),
            $formatter->display(),
            'Without an explicit language the app locale is used'
        );
    }

    // ------------------------------------------------------------- the parts

    public function test_a_missing_part_is_null(): void
    {
        // A locale with no script or region has no such name to give. ICU
        // answers with an empty string here, which is not a name.
        $formatter = $this->locale('ru');

        $this->assertSame('', locale_get_display_script('ru', 'en'), 'This test needs a locale with no script');
        $this->assertSame('', locale_get_display_region('ru', 'en'), 'This test needs a locale with no region');

        $this->assertNull($formatter->script(), 'A locale with no script has no script name');
        $this->assertNull($formatter->region(), 'A locale with no region has no region name');

        $this->assertNotNull(
            $formatter->display(),
            'A missing part does not make the whole locale unnamed'
        );
    }

    // ------------------------------------------------------------- the facade

    public function test_it_defaults_to_the_app_locale(): void
    {
        $intl = $this->intl('ru');

        $this->assertSame(
            locale_get_display_name('ru', 'en'),
            $intl->locale()->display('en'),
            'Without a locale the app one is named'
        );
    }

    public function test_the_manager_passes_the_locale_through(): void
    {
        $this->assertSame(
            locale_get_display_name('pt_BR', 'en'),
            $this->intl('en')->locale('pt_BR')->display()
        );
    }

    public function test_it_is_macroable(): void
    {
        LocaleFormatter::macro('shout', fn() => 'SHOUT');

        try {
            $this->assertSame('SHOUT', $this->locale('ru')->shout());
        } finally {
            LocaleFormatter::flushMacros();
        }

        $this->assertFalse(LocaleFormatter::hasMacro('shout'));
    }
}