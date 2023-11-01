<?php

use Carbon\CarbonPeriod;
use Codewiser\Intl\IntlDate;

if (!function_exists('intl')) {
    /**
     * Localize date and time in an app current locale.
     */
    function intl(null|DateTimeInterface|CarbonPeriod $dateTime): IntlDate
    {
        return new IntlDate($dateTime);
    }
}