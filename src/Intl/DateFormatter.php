<?php

namespace Codewiser\Intl\Intl;

use Codewiser\Intl\Intl\Traits\ConfigureCalendar;
use Codewiser\Intl\Intl\Traits\ConfigureTimezone;
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
    use Macroable, LoggerAwareTrait, ConfigureTimezone, ConfigureCalendar;

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

    /**
     * The units a relative time is reported in, coarsest first.
     */
    protected const RELATIVE_UNITS = [
        'y' => 'year',
        'm' => 'month',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    ];

    /**
     * Describe the date relative to another one, e.g. "3 days ago".
     *
     * The unit is the coarsest one that is non-zero, so a difference of 36
     * hours is "1 day". That is a calendar day rather than 24 hours, which is
     * the point: across a DST change the two are not the same length, and
     * "yesterday" should still come out as one day.
     *
     * Plural forms and number formatting come from ICU rather than from a
     * Laravel "singular|plural" line, since only the former knows that Russian
     * needs three forms and French treats zero as singular.
     */
    public function relative(?DateTimeInterface $relativeTo = null): ?string
    {
        $relativeTo ??= new DateTimeImmutable('now', new DateTimeZone($this->timezone));

        $interval = $relativeTo->diff($this->datetime);

        $count = 0;
        $unit = 'second';

        foreach (self::RELATIVE_UNITS as $field => $name) {
            if ($interval->{$field} > 0) {
                $count = $interval->{$field};
                $unit = $name;

                break;
            }
        }

        $pattern = $this->translator->get("intl::relative.$unit");

        // An unknown unit comes back from the translator as the bare key, which
        // MessageFormatter renders as itself rather than failing. A pattern ICU
        // cannot parse yields no formatter at all.
        $formatted = msgfmt_create($this->translator->getLocale(), $pattern)?->format(
            [$count, $interval->invert ? 'past' : 'future']
        );

        return $formatted ?: null;
    }
}