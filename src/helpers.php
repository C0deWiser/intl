<?php

use Codewiser\Intl\IntlDate;
use Codewiser\Intl\IntlNumber;

if (!function_exists('intl')) {
    /**
     * Localize numbers and dates in an app current locale.
     */
    function intl(null|float|int|string|DateTimeInterface|DatePeriod|\Carbon\CarbonPeriod $value): IntlNumber|IntlDate
    {
        if ($value instanceof \Carbon\CarbonPeriod) {
            $value = $value->toDatePeriod();
        }

        return is_scalar($value)
            ? new IntlNumber($value)
            : new IntlDate($value);
    }
}