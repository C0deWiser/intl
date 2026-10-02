<?php

namespace Codewiser\Intl;

use Codewiser\Intl\Intl\Transliterator;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\ServiceProvider;

class IntlServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(IntlManager::class, function (Application $app) {
            return new IntlManager(
                $app->make(Translator::class),
                config('app.timezone'),
                \IntlDateFormatter::GREGORIAN,
                'EUR',
                Transliterator::ANY_LATIN
            );
        });
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'intl');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/intl'),
        ], 'intl');
    }
}