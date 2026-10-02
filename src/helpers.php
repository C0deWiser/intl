<?php


use Codewiser\Intl\IntlManager;

if (!function_exists('intl')) {
    function intl(): IntlManager
    {
        return app(IntlManager::class);
    }
}