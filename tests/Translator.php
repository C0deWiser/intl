<?php

namespace Codewiser\Intl\Tests;

use BackedEnum;
use Illuminate\Contracts\Translation\Translator as TranslatorContract;
use Illuminate\Support\Str;
use Stringable;

/**
 * Translator double.
 *
 * Lets classes depending on the translation contract be tested without booting
 * the framework translator, but still serves the lines the package really
 * ships, so a test cannot pass against a translation that no application would
 * ever get.
 *
 * It mirrors the parts of Illuminate\Translation\Translator that this package
 * relies on: unknown keys are returned untouched, and replacements accept the
 * `:key`, `:Key` and `:KEY` forms.
 */
class Translator implements TranslatorContract
{
    /**
     * @param  string  $locale  Default locale.
     * @param  array<string, array<string, string>>  $translations  Locale => [key => line].
     */
    public function __construct(
        protected string $locale,
        protected array $translations = [],
    ) {
        $this->translations = array_replace_recursive(static::defaults(), $translations);
    }

    /**
     * The lines the package ships in lang/, flattened to [key => line].
     *
     * Read from disk rather than copied here: the calendar sentences and the ICU
     * patterns of relative.php are long enough that a hand-kept copy would
     * drift from what an application actually receives.
     *
     * @return array<string, array<string, string>>
     */
    protected static function defaults(): array
    {
        $lines = [];

        foreach (glob(__DIR__.'/../lang/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $locale = basename($dir);

            foreach (glob($dir.'/*.php') ?: [] as $file) {
                foreach ((array) require $file as $key => $line) {
                    $lines[$locale]['intl::'.basename($file, '.php').'.'.$key] = $line;
                }
            }
        }

        return $lines;
    }

    public function get($key, array $replace = [], $locale = null)
    {
        $key = (string) $key;

        $line = $this->line($key, $locale);

        if ($line === null) {
            // Matches Laravel: an unknown key comes back untouched, unreplaced.
            return $key;
        }

        return $this->makeReplacements($line, $replace);
    }

    /**
     * Picks the singular or plural form of a "singular|plural" line.
     *
     * Unlike Laravel it ignores explicit range syntax such as "[2,*]" and always
     * resolves to the first or the second form.
     */
    public function choice($key, $number, array $replace = [], $locale = null)
    {
        $key = (string) $key;

        $line = $this->line($key, $locale) ?? $key;

        // "singular|plural"; a single segment is used for every count.
        $forms = explode('|', $line);

        $form = ((int) $number === 1) ? $forms[0] : ($forms[1] ?? $forms[0]);

        return $this->makeReplacements($form, $replace);
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function setLocale($locale)
    {
        $this->locale = $locale;
    }

    /**
     * Is there a line for the key in the given locale?
     */
    public function has(string $key, ?string $locale = null): bool
    {
        return $this->line($key, $locale) !== null;
    }

    /**
     * Register a line at runtime.
     */
    public function add(string $locale, string $key, string $line): static
    {
        $this->translations[$locale][$key] = $line;

        return $this;
    }

    /**
     * Look the key up in the requested locale, then in the current one.
     */
    protected function line(string $key, ?string $locale = null): ?string
    {
        foreach (array_filter([$locale, $this->locale]) as $candidate) {
            if (isset($this->translations[$candidate][$key])) {
                return $this->translations[$candidate][$key];
            }
        }

        return null;
    }

    protected function makeReplacements(string $line, array $replace): string
    {
        if ($replace === []) {
            return $line;
        }

        $shouldReplace = [];

        foreach ($replace as $key => $value) {
            $value = match (true) {
                $value instanceof BackedEnum => $value->value,
                $value instanceof Stringable => (string) $value,
                default => $value,
            };

            $key = (string) $key;

            $shouldReplace[':' . $key] = $value;
            $shouldReplace[':' . Str::ucfirst($key)] = $value;
            $shouldReplace[':' . Str::upper($key)] = $value;
        }

        return strtr($line, $shouldReplace);
    }
}
