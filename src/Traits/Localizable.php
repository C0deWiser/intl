<?php

namespace Codewiser\Intl\Traits;

use Illuminate\Container\Container;

/**
 * @deprecated
 */
trait Localizable
{
    /**
     * Run the callback with the given locale.
     *
     * @param  string  $locale
     * @param  \Closure  $callback
     * @return mixed
     */
    public function withLocale($locale, $callback)
    {
        if (! $locale) {
            return $callback($this);
        }

        $app = Container::getInstance();

        $original = $app->getLocale();

        try {
            $app->setLocale($locale);

            return $callback($this);
        } finally {
            $app->setLocale($original);
        }
    }
}