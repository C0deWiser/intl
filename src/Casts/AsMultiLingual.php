<?php

namespace Codewiser\Intl\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Casts json attribute as multilingual.
 */
class AsMultiLingual implements CastsAttributes
{
    protected string $current_locale;
    protected ?string $fallback_locale = null;

    public function __construct(public string $type = 'string')
    {
        $this->current_locale = app()->getLocale();
        $this->fallback_locale = config('app.fallback_locale');
    }

    /**
     * Cast the given value.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return mixed
     */
    public function get($model, string $key, $value, array $attributes)
    {
        $values = $model->fromJson($model->getAttributes()[$key] ?? '');

        if (!is_array($values)) {
            $values = [];
        }

        $values = array_filter($values);

        if (isset($values[$this->current_locale])) {
            return $values[$this->current_locale];
        }

        if (isset($this->fallback_locale)) {
            if (isset($values[$this->fallback_locale])) {
                return $values[$this->fallback_locale];
            }
        }

        return current($values) ?: null;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return mixed
     */
    public function set($model, string $key, $value, array $attributes)
    {
        $values = $model->fromJson($model->getAttributes()[$key] ?? '');

        if (!is_array($values)) {
            $values = [];
        }

        if ($this->type == 'array' && is_string($value)) {
            // store as array
            $value = [$value];
        }

        $values[$this->current_locale] = $value;

        $values = array_filter($values);

        return $values ? json_encode($values) : null;
    }
}
