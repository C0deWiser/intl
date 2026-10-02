<?php

namespace Codewiser\Intl\Intl;

use Codewiser\Intl\Intl\Traits\ConfigureCalendar;
use Codewiser\Intl\Intl\Traits\ConfigureTimezone;
use Codewiser\Intl\Intl\Traits\DateShortcuts;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Traits\Macroable;
use IntlDateFormatter;
use IntlDatePatternGenerator;
use Psr\Log\LoggerAwareTrait;

class DateFormatter
{
    use Macroable, LoggerAwareTrait, ConfigureTimezone, ConfigureCalendar, DateShortcuts;

    public function __construct(
        protected DateTimeInterface $datetime,
        protected Translator $translator,
        protected string $timezone,
        protected int $calendar,
    ) {
        //
    }

    public function format(
        int $date = IntlDateFormatter::NONE,
        int $time = IntlDateFormatter::NONE
    ): ?string {

        $fmt = datefmt_create(
            $this->translator->getLocale(),
            $date,
            $time,
            $this->timezone,
            $this->calendar
        );

        return $fmt?->format($this->datetime) ?: null;
    }

    /**
     * Format the date using a CLDR skeleton, e.g. "yMMMd" for "Oct 3, 2026".
     *
     * The skeleton names the fields to show, not their order or width: ICU
     * picks the pattern the locale prefers. That is what this is for where
     * format() cannot help — it only offers the fixed LONG/MEDIUM/SHORT styles,
     * so "Sat, Oct 3" or "Q4 2026" is not expressible through them.
     *
     * A DatePeriod has no single set of fields to pick a pattern from; a range
     * arrives as a PeriodFormatter instead.
     *
     * @see https://unicode.org/reports/tr35/tr35-dates.html#Date_Field_Symbol_Table
     * @see https://www.php.net/manual/en/intldatepatterngenerator.getbestpattern.php
     */
    public function skeleton(string $skeleton): ?string
    {
        $locale = $this->translator->getLocale();

        $pattern = (new IntlDatePatternGenerator($locale))->getBestPattern($skeleton);

        if ($pattern === '') {
            // ICU has no field symbol to map, so there is nothing to format.
            return null;
        }

        $fmt = new IntlDateFormatter(
            $locale,
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE,
            $this->timezone,
            $this->calendar,
            $pattern
        );

        return $fmt->format($this->datetime) ?: null;
    }
}