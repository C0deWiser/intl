<?php

namespace Codewiser\Intl\Casts;

use ArrayAccess;
use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Casts\Json;
use Illuminate\Database\Eloquent\Model;
use JsonSerializable;

/**
 * Multilingual attribute holds an array of localized values:
 *
 * [
 *  'en' => 'Michael',
 *  'ru' => 'Михаил',
 *  'es' => 'Miguel'
 * ]
 */
class Multilingual implements Castable, Arrayable, JsonSerializable, ArrayAccess
{
    protected array $values = [];

    public static ?string $currentLocale = null;
    public static ?string $fallbackLocale = null;

    public static function useLocale(string $locale, ?string $fallback_locale = null): void
    {
        self::$currentLocale = $locale;
        if ($fallback_locale) {
            self::$fallbackLocale = $fallback_locale;
        }
    }

    public function getLocale(): string
    {
        return locale_canonicalize(self::$currentLocale ?? 'en');
    }

    public function getFallbackLocale(): string
    {
        return locale_canonicalize(self::$fallbackLocale ?? 'en');
    }

    public function __construct(null|string|array|Arrayable $values)
    {
        if (is_string($values)) {
            $values = [$this->getLocale() => $values];
        }

        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        if (is_array($values)) {
            $this->values = $values;
        }
    }

    public function __toString(): string
    {
        $value = $this->toString();

        if (is_array($value)) {
            $value = json_encode($value);
        }

        return (string) $value;
    }

    /**
     * Get value in current locale.
     */
    public function toString(): mixed
    {
        $values = $this->values;

        /**
         * Keys may be: en, en_GB, en-GB
         * Lang may be: en, en_GB, en-GB
         */
        $search = function (array $values, string $lang) {
            // Direct match
            if (isset($values[$lang])) {
                return $values[$lang];
            }

            // Filter matches
            $locales = array_filter(
                array_keys($values),
                fn($locale) => locale_filter_matches($locale, $lang),
            );

            // From short to long
            usort($locales, function ($a, $b) {
                if (strlen($a) == strlen($b)) {
                    return 0;
                }
                return (strlen($a) < strlen($b)) ? -1 : 1;
            });

            // Return best match
            return $locales ? $values[$locales[0]] : null;
        };

        reset($values);

        $value =
            $search($this->values, $this->getLocale()) ??
            $search($this->values, $this->getFallbackLocale()) ??
            current($values) ?? '';

        if (is_string($value)) {
            $value = trim($value);
        }

        return $value;
    }

    /**
     * Has no values?
     */
    public function isEmpty(): bool
    {
        return count(array_filter($this->values)) === 0;
    }

    /**
     * Get missing translations.
     */
    public function missing(string|array|Arrayable $locales): array
    {
        if (is_string($locales)) {
            $locales = [$locales];
        }

        if ($locales instanceof Arrayable) {
            $locales = $locales->toArray();
        }

        return array_filter(
            $locales,
            fn($locale) => !isset($this->values[$locale instanceof BackedEnum ? $locale->value : $locale])
        );
    }

    /**
     * Get present translations.
     */
    public function present(): array
    {
        return array_keys($this->values);
    }

    public function toArray(): array
    {
        return $this->values;
    }

    public function jsonSerialize(): mixed
    {
        return $this->isEmpty() ? null : $this->toString();
    }

    public function offsetExists(mixed $offset): bool
    {
        $offset = $offset instanceof BackedEnum ? $offset->value : $offset;

        return isset($this->values[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        $offset = $offset instanceof BackedEnum ? $offset->value : $offset;

        return $this->values[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $offset = $offset instanceof BackedEnum ? $offset->value : $offset;

        $this->values[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        $offset = $offset instanceof BackedEnum ? $offset->value : $offset;

        if (isset($this->values[$offset])) {
            unset($this->values[$offset]);
        }
    }

    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class ($arguments) implements CastsAttributes {

            public function __construct(protected array $arguments)
            {
                //
            }

            public function get(Model $model, string $key, mixed $value, array $attributes): ?Multilingual
            {
                if (is_string($value)) {
                    $value = Json::decode($value);
                }

                return is_array($value) ? new Multilingual($value) : null;
            }

            public function set(Model $model, string $key, mixed $value, array $attributes): ?string
            {
                if ($value instanceof Multilingual) {
                    $value = $value->toArray();
                }

                if (is_array($value) && !array_is_list($value)) {
                    // Full replace
                    $values = $value;
                } else {
                    // Replace current locale
                    $values = $attributes[$key] ?? null;
                    $values = new Multilingual($values ? Json::decode($attributes[$key]) : []);
                    $values[$values->getLocale()] = $value;
                    $values = $values->toArray();
                }

                $values = array_filter($values);

                return $values ? Json::encode($values) : null;
            }
        };
    }

    public static function array(): string
    {
        return MultilingualArray::class;
    }
}
