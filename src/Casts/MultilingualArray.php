<?php

namespace Codewiser\Intl\Casts;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;

/**
 * MultilingualArray attribute holds an array of Multilingual.
 */
class MultilingualArray implements Castable
{
    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class($arguments) implements CastsAttributes
        {
            public function __construct(public array $arguments)
            {
                //
            }

            public function get(Model $model, string $key, mixed $value, array $attributes)
            {
                if (is_string($value)) {
                    $value = json_decode($value, true);
                }

                if (is_array($value) && array_is_list($value)) {
                    $value = array_map(fn($item) => new Multilingual($item), $value);
                }

                return $value;
            }

            public function set(Model $model, string $key, mixed $value, array $attributes)
            {
                // На вход может прилететь Collection
                if ($value instanceof Arrayable) {
                    $value = $value->toArray();
                }

                // На вход может прилететь массив
                if (is_array($value) && array_is_list($value)) {
                    // Понизим Multilingual до массива
                    $value = array_map(fn($item) => $item instanceof Arrayable ? $item->toArray() : $item, $value);
                    // Каждый элемент должен быть массивом
                    $value = array_filter($value, fn($item) => is_array($item));
                }

                return $value ? json_encode($value) : null;
            }
        };
    }
}