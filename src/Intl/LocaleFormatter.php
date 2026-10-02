<?php

namespace Codewiser\Intl\Intl;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Traits\Macroable;
use Psr\Log\LoggerAwareTrait;

/**
 * Names for a locale, written in another one.
 *
 * Needed wherever a language has to be shown to a person rather than resolved:
 * a language switcher, the label of a translation, an error naming the locale
 * it gave up on.
 *
 * Every method takes the locale to write the name in, defaulting to the one the
 * app is currently in. A locale ICU cannot name yields null rather than an echo
 * of what was asked for.
 */
class LocaleFormatter
{
    use Macroable, LoggerAwareTrait;

    /**
     * @param  string  $localeToInspect  The locale to handle, e.g. "zh_Hant_TW".
     */
    public function __construct(
        protected Translator $translator,
        protected string $localeToInspect,
    ) {
        //
    }

    /**
     * The language name, e.g. "Russian".
     */
    public function language(?string $locale = null): ?string
    {
        return $this->name('locale_get_display_language', 'locale_get_primary_language', $locale);
    }

    /**
     * The script name, e.g. "Traditional Han".
     */
    public function script(?string $locale = null): ?string
    {
        return $this->name('locale_get_display_script', 'locale_get_script', $locale);
    }

    /**
     * The region name, e.g. "United States".
     */
    public function region(?string $locale = null): ?string
    {
        return $this->name('locale_get_display_region', 'locale_get_region', $locale);
    }

    /**
     * The whole locale name, e.g. "Chinese (Traditional, Taiwan)".
     */
    public function display(?string $locale = null): ?string
    {
        // The whole name leads with the language, so an unnamed one leaves an
        // echo of the tag wrapped in brackets. "xx (YY)" names nothing, and the
        // echo is no longer equal to the subtag for the check below to catch on
        // its own.
        if ($this->language($locale) === null) {
            return null;
        }

        return $this->name('locale_get_display_name', 'locale_get_primary_language', $locale);
    }

    /**
     * Runs one of the locale_get_display_* functions and checks the answer.
     *
     * ICU does not admit that it cannot name something: it echoes the subtag
     * back, so a reader is shown a language called "xx". An answer identical to
     * the subtag is therefore not a name.
     *
     * @param  callable(string, string): (string|false)  $display
     * @param  callable(string): string  $subtag  The subtag $display might echo.
     */
    protected function name(callable $display, callable $subtag, ?string $inLocale): ?string
    {
        $locale = $this->localeToInspect;

        $inLocale = $inLocale ?? $this->translator->getLocale();

        $name = $display($locale, $inLocale);

        if ($name === false || $name === '') {
            return null;
        }

        $raw = $subtag($locale);

        // "und" and "root" carry no language at all, and ICU calls both
        // "Unknown language", which is a phrase about ICU rather than a name.
        return $raw === '' || $name === $raw ? null : $name;
    }
}