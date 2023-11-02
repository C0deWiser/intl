<?php

namespace Codewiser\Intl\Traits;

use Codewiser\Intl\Casts\AsMultiLingual;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Traits\Localizable;

/**
 * Model has localized attributes.
 *
 * @property array $i18n
 *
 * @mixin Model
 */
trait HasLocalizations
{
    use Localizable;

    protected static function bootHasLocalizations(): void
    {
        static::retrieved(
            fn(Model $model) => $model->append('i18n')
        );
        static::saved(
            fn(Model $model) => $model->append('i18n')
        );
    }

    /**
     * Appends all localized attributes with locale as array prefix.
     *
     * Helps to make multilingual searchable array.
     */
    public function toLocalizedArray(): array
    {
        $array = $this->toArray();

        unset($array['i18n']);

        foreach ($this->casts as $attribute => $type) {
            if ($type == AsMultiLingual::class) {
                unset($array[$attribute]);

                $values = json_decode($this->getRawOriginal($attribute), true) ?? [];

                foreach ($values as $locale => $value) {
                    $array["{$locale}_$attribute"] = $value;
                }
            }
        }

        return $array;
    }

    protected function i18n(): Attribute
    {
        return Attribute::make(
            get: function () {
                $values = [];
                $locale = app()->getLocale();

                foreach ($this->casts as $attribute => $casts) {
                    if ($casts == AsMultiLingual::class) {

                        $raw = $this->fromJson($this->getAttributes()[$attribute] ?? '');

                        if (!is_array($raw)) {
                            $raw = [];
                        }

                        $values[$attribute] = $raw[$locale] ?? null;
                    }
                }

                return $values;
            }
        );
    }
}
